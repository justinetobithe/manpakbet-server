<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Post;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            ['title' => 'Midnight Jackpot Rush', 'subtitle' => 'Progressive prize pools refresh every night. Spin in before the clock strikes twelve.', 'image' => '/banners/hero-jackpot.svg', 'link_url' => '/games/slot', 'cta_label' => 'Spin Now', 'placement' => 'hero', 'sort_order' => 1],
            ['title' => 'Live Tables, Real Dealers', 'subtitle' => 'Baccarat, roulette and color games streamed in HD around the clock.', 'image' => '/banners/hero-live.svg', 'link_url' => '/games/live', 'cta_label' => 'Join a Table', 'placement' => 'hero', 'sort_order' => 2],
            ['title' => '100% Welcome Boost', 'subtitle' => 'Double your first deposit up to ₱5,000 and start strong.', 'image' => '/banners/hero-welcome.svg', 'link_url' => '/promotions', 'cta_label' => 'Claim Bonus', 'placement' => 'hero', 'sort_order' => 3],
            ['title' => 'Cast & Catch', 'subtitle' => 'Fishing arcades with multiplier bosses.', 'image' => '/banners/side-fishing.svg', 'link_url' => '/games/fishing', 'cta_label' => 'Play', 'placement' => 'side', 'sort_order' => 1],
            ['title' => 'Daily Cashback', 'subtitle' => 'Up to 8% back on every bet, every day.', 'image' => '/banners/side-cashback.svg', 'link_url' => '/promotions', 'cta_label' => 'Details', 'placement' => 'side', 'sort_order' => 2],
        ];

        foreach ($banners as $banner) {
            Banner::query()->updateOrCreate(['title' => $banner['title']], $banner + ['is_active' => true]);
        }

        $promotions = [
            ['title' => '100% Welcome Boost', 'tag' => 'Welcome', 'summary' => 'Double your first deposit up to ₱5,000.', 'image' => '/banners/promo-welcome.svg', 'cta_label' => 'Deposit Now', 'cta_url' => '/account/wallet'],
            ['title' => 'Daily 8% Cashback', 'tag' => 'Cashback', 'summary' => 'Automatic rebates credited every midnight.', 'image' => '/banners/promo-cashback.svg', 'cta_label' => 'Learn More', 'cta_url' => '/promotions'],
            ['title' => 'Weekend Reload 50%', 'tag' => 'Reload', 'summary' => 'Top up on Saturday or Sunday for a 50% boost.', 'image' => '/banners/promo-reload.svg', 'cta_label' => 'Reload', 'cta_url' => '/account/wallet'],
            ['title' => 'Refer & Earn ₱300', 'tag' => 'Referral', 'summary' => 'Invite a friend and both of you get rewarded.', 'image' => '/banners/promo-referral.svg', 'cta_label' => 'Invite', 'cta_url' => '/account'],
            ['title' => 'VIP Level Up Rewards', 'tag' => 'VIP', 'summary' => 'Climb tiers and unlock bigger perks every month.', 'image' => '/banners/promo-vip.svg', 'cta_label' => 'See Tiers', 'cta_url' => '/promotions'],
            ['title' => 'Live Casino Streak', 'tag' => 'Live', 'summary' => 'Win 5 hands in a row and earn a bonus chip.', 'image' => '/banners/promo-live.svg', 'cta_label' => 'Play Live', 'cta_url' => '/games/live'],
        ];

        $content = "<p>Make a qualifying deposit and the bonus is credited automatically to your wallet.</p><ul><li>Minimum deposit of ₱100 applies.</li><li>Bonus funds carry a 10x turnover requirement.</li><li>One claim per player, device and payment account.</li><li>ManpakBet reserves the right to amend or cancel this promotion at any time.</li></ul>";

        foreach ($promotions as $i => $promo) {
            Promotion::query()->updateOrCreate(
                ['slug' => Str::slug($promo['title'])],
                $promo + ['content' => $content, 'sort_order' => $i + 1, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3)]
            );
        }

        $posts = [
            ['title' => 'ManpakBet gets a fresh new look', 'category' => 'announcement', 'excerpt' => 'A faster lobby, smarter search and a brand new wallet experience.', 'is_pinned' => true],
            ['title' => 'Scheduled maintenance this Sunday', 'category' => 'announcement', 'excerpt' => 'Some live tables will be offline from 2:00 AM to 4:00 AM.'],
            ['title' => 'Five tips for responsible play', 'category' => 'guide', 'excerpt' => 'Set limits, take breaks and keep gaming fun.'],
            ['title' => 'New fishing arcade titles have landed', 'category' => 'news', 'excerpt' => 'Boss multipliers and co-op cannons now in the lobby.'],
            ['title' => 'Grand Color Game Tournament', 'category' => 'event', 'excerpt' => 'Compete for a ₱100,000 prize pool all month long.'],
        ];

        foreach ($posts as $i => $post) {
            Post::query()->updateOrCreate(
                ['slug' => Str::slug($post['title'])],
                $post + [
                    'content' => '<p>' . $post['excerpt'] . '</p><p>Stay tuned to the ManpakBet news page and your inbox for the latest updates. If you have any questions, our support team is available 24/7.</p>',
                    'image' => '/banners/news-' . (($i % 3) + 1) . '.svg',
                    'is_published' => true,
                    'published_at' => now()->subDays($i),
                ]
            );
        }
    }
}
