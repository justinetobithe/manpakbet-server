<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeCasinoPlusGameBanners extends Command
{
    protected $signature = 'games:scrape-casinoplus-banners
        {--url=https://www.casinoplus.com.ph/games/}
        {--force}
        {--dry-run}
        {--limit=0}
        {--sleep=120}
        {--timeout=240}
        {--debug}';

    protected $description = 'Scrape CasinoPlus game images, save as banner webp, and update games.banner';

    public function handle(): int
    {
        $url = (string) $this->option('url');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');
        $timeout = (int) $this->option('timeout');
        $debug = (bool) $this->option('debug');

        $this->info("Rendering page: {$url}");

        $items = $this->renderItems($url, $timeout);

        if (count($items) === 0) {
            $html = $this->renderHtml($url, $timeout);

            if ($debug) {
                Storage::disk('local')->put('scrape/casinoplus.html', $html);
                $this->warn('Saved: storage/app/scrape/casinoplus.html');
            }

            if (!is_string($html) || trim($html) === '') {
                $this->error('Empty HTML from renderer.');
                return self::FAILURE;
            }

            $items = $this->parseItemsFromHtml($html, $url);
        }

        $items = $this->dedupeItems($items);

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $total = count($items);
        $this->info("Found: {$total} items");

        if ($total === 0) {
            $this->error('No game items extracted. If storage/app/scrape/casinoplus.html shows a block page, headless is blocked.');
            return self::FAILURE;
        }

        $manager = new ImageManager(new Driver());

        $saved = 0;
        $updatedExisting = 0;
        $skippedNoGame = 0;
        $skippedExists = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $srcRaw = trim((string) ($item['src'] ?? ''));

            if ($name === '' || $srcRaw === '') {
                $failed++;
                $bar->advance();
                continue;
            }

            $src = $this->normalizeUrl($srcRaw, $url);

            $slug = Str::slug($name);
            if ($slug === '') {
                $failed++;
                $bar->advance();
                continue;
            }

            $game = Game::query()
                ->where('slug', $slug)
                ->orWhere('name', $name)
                ->first();

            if (!$game) {
                $skippedNoGame++;
                $bar->advance();
                continue;
            }

            $fileName = "{$slug}-banner.webp";
            $diskPath = "games/{$fileName}";
            $publicPath = "/storage/games/{$fileName}";

            if (!$force && Storage::disk('public')->exists($diskPath)) {
                $skippedExists++;

                if (!$dryRun && (string) $game->banner !== $publicPath) {
                    $game->banner = $publicPath;
                    $game->save();
                    $updatedExisting++;
                }

                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $bar->advance();
                continue;
            }

            try {
                $imgRes = Http::withHeaders([
                    'User-Agent' => $this->ua(),
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => $url,
                ])->timeout(60)->get($src);

                if (!$imgRes->successful()) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $bytes = $imgRes->body();
                if (!is_string($bytes) || $bytes === '') {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $image = $manager->read($bytes);
                $encoded = $image->toWebp(85);

                Storage::disk('public')->put($diskPath, (string) $encoded);

                $game->banner = $publicPath;
                $game->save();

                $saved++;

                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            } catch (\Throwable $e) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Saved new: {$saved}");
        $this->info("Updated existing DB banner: {$updatedExisting}");
        $this->info("Skipped (not in DB): {$skippedNoGame}");
        $this->info("Skipped (file exists): {$skippedExists}");
        $this->info("Failed: {$failed}");

        return self::SUCCESS;
    }

    private function ua(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
    }

    private function makeBrowsershot(string $url, int $timeoutSeconds): Browsershot
    {
        $bs = Browsershot::url($url)
            ->userAgent($this->ua())
            ->setOption('args', [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--window-size=1400,900',
            ])
            ->setOption('waitUntil', 'domcontentloaded')
            ->timeout($timeoutSeconds);

        $chrome = env('PUPPETEER_EXECUTABLE_PATH');
        if (is_string($chrome) && trim($chrome) !== '') {
            $bs->setChromePath($chrome);
        }

        return $bs;
    }

    private function renderItems(string $url, int $timeoutSeconds): array
    {
        $bs = $this->makeBrowsershot($url, $timeoutSeconds)->waitFor('.box-game[data-game] img', 120000);

        $json = (string) $bs->evaluate(<<<'JS'
(async () => {
  const sleep = (ms) => new Promise(r => setTimeout(r, ms));
  const clean = (s) => (s || '').toString().replace(/\s+/g, ' ').trim();

  await sleep(1200);

  try {
    window.scrollTo(0, 0);
    await sleep(600);
    for (let i = 0; i < 8; i++) {
      window.scrollTo(0, document.body.scrollHeight);
      await sleep(700);
    }
    window.scrollTo(0, 0);
    await sleep(600);
  } catch (e) {}

  const cards = Array.from(document.querySelectorAll('.box-game[data-game]'));
  const out = [];

  for (const el of cards) {
    const name = clean(el.getAttribute('data-game'));
    if (!name) continue;

    const img = el.querySelector('img');
    if (!img) continue;

    const src = clean(img.getAttribute('src') || img.getAttribute('data-src') || img.currentSrc || '');
    if (!src) continue;

    out.push({ name, src });
  }

  const uniq = {};
  for (const it of out) {
    const key = it.name.toLowerCase() + '|' + it.src;
    uniq[key] = it;
  }

  return JSON.stringify(Object.values(uniq));
})()
JS);

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return [];
        }

        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $src = trim((string) ($row['src'] ?? ''));
            if ($name === '' || $src === '') {
                continue;
            }
            $items[] = ['name' => $name, 'src' => $src];
        }

        return $items;
    }

    private function renderHtml(string $url, int $timeoutSeconds): string
    {
        return (string) $this->makeBrowsershot($url, $timeoutSeconds)->bodyHtml();
    }

    private function parseItemsFromHtml(string $html, string $baseUrl): array
    {
        $crawler = new Crawler($html);
        $nodes = $crawler->filter('.box-game[data-game]');

        if ($nodes->count() === 0) {
            $nodes = $crawler->filter('[data-game]');
        }

        $items = [];
        foreach ($nodes as $el) {
            $node = new Crawler($el);

            $name = trim((string) $node->attr('data-game'));
            if ($name === '') {
                continue;
            }

            $src = '';
            if ($node->filter('img')->count() > 0) {
                $src = trim((string) $node->filter('img')->first()->attr('src'));
            }

            if ($src === '') {
                continue;
            }

            $items[] = ['name' => $name, 'src' => $this->normalizeUrl($src, $baseUrl)];
        }

        return $items;
    }

    private function dedupeItems(array $items): array
    {
        $uniq = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $name = trim((string) ($it['name'] ?? ''));
            $src = trim((string) ($it['src'] ?? ''));
            if ($name === '' || $src === '') {
                continue;
            }
            $key = mb_strtolower($name) . '|' . $src;
            $uniq[$key] = ['name' => $name, 'src' => $src];
        }
        return array_values($uniq);
    }

    private function normalizeUrl(string $src, string $baseUrl): string
    {
        $src = trim($src);

        if (Str::startsWith($src, ['http://', 'https://'])) {
            return $src;
        }

        if (Str::startsWith($src, '//')) {
            return 'https:' . $src;
        }

        $base = rtrim($baseUrl, '/');

        if (Str::startsWith($src, '/')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            $host = parse_url($base, PHP_URL_HOST) ?: '';
            $port = parse_url($base, PHP_URL_PORT);

            $origin = $scheme . '://' . $host;
            if ($port) {
                $origin .= ':' . $port;
            }

            return $origin . $src;
        }

        return $base . '/' . ltrim($src, '/');
    }
}
