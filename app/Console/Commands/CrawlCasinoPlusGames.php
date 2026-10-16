<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\DomCrawler\Crawler;

class CrawlCasinoPlusGames extends Command
{
    protected $signature = 'games:crawl-casinoplus
        {--base=https://www.casinoplus.com.ph/games/}
        {--catalog=database/data/casinoplus-games.json}
        {--images-dir=storage/app/public/games : Folder (relative to the API root) that receives the images}
        {--image-prefix=/storage/games : Public URL prefix stored on each game}
        {--skip-images : Only refresh the catalog file}
        {--force : Re-download images that already exist}
        {--concurrency=10}';

    protected $description = 'Crawl the CasinoPlus lobby into a game catalog and download each game image';

    private const CATEGORY_PAGES = [
        'Live Games' => 'live',
        'Poker' => 'poker',
        'Sports' => 'sports',
        'Slot Machines' => 'slot',
        'Slot Games' => 'slot',
    ];

    private const PROVIDER_NAMES = [
        'PG SOFT' => 'PG Soft',
        'EVOLIVE' => 'Evolution',
        'ENRT' => 'Endorphina',
        'YGG' => 'Yggdrasil',
        'PROD-YGR' => 'YGR',
        'PP-POP' => 'Pragmatic Play',
        'PP Live' => 'Pragmatic Live',
        'EEZE Slot' => 'EEZE',
        'COLORGAME' => 'Color Game',
        'BACCARAT' => 'Baccarat Live',
    ];

    private const FISHING_WORDS = ['fishing', 'fish ', 'fish hunter', 'ocean king', 'star hunter', 'shark hunter', 'cai shen fishing'];

    public function handle(): int
    {
        $base = rtrim((string) $this->option('base'), '/') . '/';

        $this->info('Fetching lobby…');
        $lobby = $this->parseCards($this->fetch($base));

        if (!$lobby) {
            $this->error('No game cards found on the lobby page.');
            return self::FAILURE;
        }

        $categoryBySlug = [];
        foreach (self::CATEGORY_PAGES as $page => $category) {
            $this->line("Fetching category: {$page}");
            foreach ($this->parseCards($this->fetch($base . rawurlencode($page) . '/')) as $card) {
                $categoryBySlug[Str::slug($card['name'])] ??= $category;
            }
        }

        $catalog = [];
        $seen = [];

        foreach ($lobby as $index => $card) {
            $provider = $this->providerName($card['provider']);
            $slug = Str::slug($card['name']);

            if ($slug === '') {
                continue;
            }

            if (isset($seen[$slug])) {
                if ($seen[$slug] === $provider) {
                    continue;
                }
                $slug = Str::slug($card['name'] . ' ' . $provider);
                if (isset($seen[$slug])) {
                    continue;
                }
            }

            $seen[$slug] = $provider;

            $catalog[] = [
                'name' => $card['name'],
                'slug' => $slug,
                'provider' => $provider,
                'provider_slug' => Str::slug($provider),
                'category' => $this->category($card['name'], $categoryBySlug[Str::slug($card['name'])] ?? null),
                'rank' => $index + 1,
                'image_url' => $card['image'],
                'image' => rtrim((string) $this->option('image-prefix'), '/') . "/{$slug}.webp",
            ];
        }

        $path = base_path((string) $this->option('catalog'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        $this->info(sprintf('Catalog saved: %d games → %s', count($catalog), $this->option('catalog')));

        if (!$this->option('skip-images')) {
            $this->downloadImages($catalog, $base);
        }

        return self::SUCCESS;
    }

    private function fetch(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-PH,en;q=0.9',
        ])->timeout(90)->retry(3, 1500)->get($url);

        return $response->successful() ? $response->body() : '';
    }

    private function parseCards(string $html): array
    {
        if ($html === '') {
            return [];
        }

        return (new Crawler($html))->filter('.r-box-game[data-game]')->each(function (Crawler $node) {
            $name = trim(preg_replace('/\s+/', ' ', html_entity_decode((string) $node->attr('data-game'))));
            $image = $node->filter('img')->count() ? trim((string) $node->filter('img')->first()->attr('src')) : '';

            return [
                'name' => $name,
                'provider' => trim(preg_replace('/\s+/', ' ', (string) $node->attr('data-provider'))),
                'image' => Str::startsWith($image, '//') ? "https:{$image}" : $image,
            ];
        });
    }

    private function providerName(string $raw): string
    {
        $raw = $raw !== '' ? $raw : 'Other';

        return self::PROVIDER_NAMES[$raw] ?? $raw;
    }

    private function category(string $name, ?string $fromPage): string
    {
        $lower = ' ' . Str::lower($name) . ' ';

        if (in_array($fromPage, ['live', 'poker', 'sports'], true)) {
            return $fromPage;
        }

        foreach (self::FISHING_WORDS as $word) {
            if (Str::contains($lower, $word)) {
                return 'fishing';
            }
        }

        return $fromPage ?? 'slot';
    }

    private function downloadImages(array $catalog, string $referer): void
    {
        $dir = (string) $this->option('images-dir');
        $root = Str::startsWith($dir, ['/', '\\']) || preg_match('/^[A-Za-z]:/', $dir) ? $dir : base_path($dir);
        File::ensureDirectoryExists($root);
        $disk = Storage::build(['driver' => 'local', 'root' => $root]);
        $force = (bool) $this->option('force');
        $pending = array_values(array_filter($catalog, fn ($game) => $game['image_url'] && ($force || !$disk->exists("{$game['slug']}.webp"))));

        if (!$pending) {
            $this->info('All images already downloaded.');
            return;
        }

        $manager = new ImageManager(new Driver());
        $saved = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar(count($pending));
        $bar->start();

        foreach (array_chunk($pending, max(1, (int) $this->option('concurrency'))) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($game) => $pool->as($game['slug'])
                    ->withHeaders(['Referer' => $referer, 'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8'])
                    ->timeout(60)
                    ->get($game['image_url']),
                $chunk
            ));

            foreach ($chunk as $game) {
                $response = $responses[$game['slug']] ?? null;

                try {
                    if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful() || $response->body() === '') {
                        throw new \RuntimeException('download failed');
                    }

                    $bytes = $response->body();
                    $isWebp = substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP';
                    $disk->put("{$game['slug']}.webp", $isWebp ? $bytes : (string) $manager->read($bytes)->toWebp(85));
                    $saved++;
                } catch (\Throwable) {
                    $failed++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Images saved: {$saved}");

        if ($failed) {
            $this->warn("Images failed: {$failed}");
        }
    }
}
