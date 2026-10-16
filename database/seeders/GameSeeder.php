<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $catId = GameCategory::pluck('id', 'slug')->all();
        $provId = GameProvider::pluck('id', 'slug')->all();

        $names = $this->rawGameNames();

        $clean = $this->normalizeNames($names);
        $clean = array_values(array_unique($clean));

        $sortCounters = [];

        foreach ($clean as $name) {
            $providerSlug = $this->detectProviderSlug($name);
            $categorySlug = $this->detectCategorySlug($name, $providerSlug);

            if (!isset($catId[$categorySlug])) {
                continue;
            }

            $providerId = $providerSlug && isset($provId[$providerSlug]) ? $provId[$providerSlug] : null;

            if (!isset($sortCounters[$categorySlug])) {
                $sortCounters[$categorySlug] = 0;
            }
            $sortCounters[$categorySlug]++;

            $slug = Str::slug($name);

            $thumb = '/storage/games/' . $slug . '.webp';
            $banner = '/storage/games/' . $slug . '-banner.webp';
            $launch = '/play/' . $slug;

            Game::updateOrCreate(
                ['slug' => $slug],
                [
                    'game_category_id' => $catId[$categorySlug],
                    'game_provider_id' => $providerId,
                    'name' => $name,
                    'provider' => $providerSlug ? strtoupper(str_replace('-', ' ', $providerSlug)) : null,
                    'thumbnail' => $thumb,
                    'banner' => $banner,
                    'launch_url' => $launch,
                    'sort_order' => $sortCounters[$categorySlug],
                    'is_active' => true,
                ]
            );
        }
    }

    private function normalizeNames(array $items): array
    {
        $badExact = ['undefined', '₱', 'jackpot', 'min'];
        $out = [];

        foreach ($items as $raw) {
            $s = trim((string) $raw);
            if ($s === '') continue;

            $s = preg_replace('/\s+/', ' ', $s);
            $s = trim($s);

            $lower = mb_strtolower($s);

            if (in_array($lower, $badExact, true)) continue;
            if (preg_match('/^₱?\s?\d[\d,]*\.\d{1,2}$/', $s)) continue;
            if (preg_match('/^₱?\s?\d[\d,]*$/', $s)) continue;
            if (preg_match('/^(jackpot\s*)?₱/i', $s)) continue;
            if (Str::startsWith($lower, 'jackpot')) continue;
            if ($lower === 'to' || $lower === 'live' || $lower === 'new') continue;

            $s = str_replace(['’', '“', '”'], ["'", '"', '"'], $s);
            $s = trim($s, " \t\n\r\0\x0B-–—");

            if (mb_strlen($s) < 2) continue;

            $out[] = $s;
        }

        return $out;
    }

    private function detectProviderSlug(string $name): ?string
    {
        $n = mb_strtolower($name);

        if (Str::contains($n, ['jili', 'super ace', 'tongits', 'pusoy', 'color game', 'drop ball', 'boxing king', 'fortune gems'])) {
            return 'jili';
        }

        if (Str::contains($n, ['pg soft', 'pgsoft', 'mahjong ways', 'fortune tiger', 'fortune ox', 'fortune mouse', 'ways of the qilin', 'garuda', 'medusa'])) {
            return 'pgsoft';
        }

        if (Str::contains($n, ['pragmatic', 'sweet bonanza', 'gates of olympus', 'starlight princess', 'big bass', 'the dog house', 'wolf gold', 'sugar rush', 'fruit party', 'hot to burn'])) {
            return 'pragmatic-play';
        }

        return null;
    }

    private function detectCategorySlug(string $name, ?string $providerSlug): string
    {
        $n = mb_strtolower($name);

        if (Str::contains($n, ['fishing', 'fish', 'ocean king', 'star hunter', 'mega fishing', 'cai shen fishing', 'dragon fishing'])) {
            return 'fishing';
        }

        if (Str::contains($n, ['basketball', 'soccer', 'volleyball', 'tennis', 'baseball', 'football', 'boxing', 'sportbet', 'sports'])) {
            return 'sports';
        }

        if (Str::contains($n, ['poker', "hold'em", 'holdem', 'teen patti', 'pai gow', 'caribbean stud', 'three card poker', 'bac bo', 'sic bo', 'sicbo', 'fan tan'])) {
            return 'poker';
        }

        if (Str::contains($n, ['baccarat', 'roulette', 'blackjack', 'crazy time', 'monopoly live', 'dream catcher', 'deal or no deal', 'andar bahar', 'war', 'craps'])) {
            return 'live';
        }

        if (Str::startsWith($n, ['new:', 'new ']) || Str::contains($n, ['xmas', 'christmas', 'year of', 'festival'])) {
            return 'new';
        }

        if ($providerSlug === 'jili' && Str::contains($n, ['color game', 'super ace', 'dragon tiger', 'tongits', 'pusoy', 'drop ball'])) {
            return 'popular';
        }

        return 'slot';
    }

    private function rawGameNames(): array
    {
        return [
            'Speed Baccarat A',
            'Super Ace',
            'Pula Puti',
            'Super Color Game',
            'Roulette',
            'Baccarat',
            'Crazy Time',
            'Lightning Baccarat',
            'Dragon Tiger',
            'Super Ace 2',
            'Marble Race',
            'Color Game',
            'Super Ace Jackpot',
            'Golden Wealth Baccarat',
            'Drop Ball',
            'Funky Time',
            'Black Jack',
            'Lightning Roulette',
            'JJBX Gold',
            'Blackjack Grand Vip',
            'Super Ace Deluxe',
            'Football Studio Dice',
            'SicBo',
            'Fortune Gems',
            'Crazy Time A',
            'Min ₱100 Blackjack',
            'Baccarat Squeeze',
            'Min ₱500 Blackjack',
            'Speed Vip Blackjack A',
            'Dead Or Alive: Saloon',
            'Vip Roulette',
            'Extra Chilli Epic Spins',
            'Monopoly Live',
            'Blackjack Silver A',
            'Fortune Gems 2',
            'American Roulette',
            'Sugar Bang Bang',
            'Pai Gow Poker',
            'Bac Bo',
            'Infinite Blackjack',
            'Jackpot Fishing',
            'Football Studio',
            'Bao Zhu Zhao Fu',
            'Super Sic Bo',
            'Mines',
            'Xxxtreme Lightning Roulette',
            'Instant Roulette',
            'Min ₱1K Blackjack',
            'Lightning Blackjack',
            'Blackjack Fortune Vip',
            'Peek Baccarat',
            "Texas Hold'Em Bonus Poker",
            'Classic Baccarat',
            'Emperor Speed Baccarat A',
            'Speed Baccarat',
            'Blackjack Party',
            'Poker Win',
            'No Commission Baccarat',
            'Three Card Poker / Triple Card Poker',
            'Lucky Fortunes',
            'Teen Patti',
            'Min ₱50 Baccarat',
            'Blackjack A',
            'Commission Baccarat',
            'Speed Roulette',
            'Blackjack Vip 1',
            'Emperor Dragon Tiger',
            'Colorgame 60X',
            'Fortune Coins',
            'Fan Tan',
            'High Limit Baccarat',
            'Baccarat Control Squeeze',
            'Crazy Coin Flip',
            'Lightning Dice',
            "Gonzo's Treasure Map",
            'Caribbean Stud Poker',
            'Golden Empire',
            'Super Andar Bahar',
            'Wanted Dead Or A Wild',
            'Infinite Free Bet Blackjack',
            'Cloud Princess',
            'Salon Privé Blackjack A',
            'Rise Of Ymir',
            "2 Hand Casino Hold'Em",
            "Casino Hold'Em",
            'Le Pharaoh',
            'Speed Auto Roulette',
            'Ze Zeus',
            'Auto-Roulette Vip',
            'Octo Attack',
            'Salon Privé Baccarat A',
            "Ultimate Texas Hold'Em",
            'Donny Dough',
            'Salon Privé Roulette',
            'Hand Of Anubis',
            'Power Blackjack',
            'Toshi Video Club',
            'Emperor Roulette',
            'RIP City',
            'Craps',
            'Get The CHEESE',
            'Auto-Roulette',
            'Fist Of Destruction',
            'Double Ball Roulette',
            '2 Wild 2 Die',
            'Dream Catcher',
            "Dragon's Domain",
            'Immersive Roulette',
            'Dork Unit',
            'Imperial Quest',
            'Rotten',
            'Balloon Race',
            'Le Bandit',
            'Cash or Crash',
            'Money Coming',
            'Crazy Pachinko',
            'Fortune Gems 3',
            'Deal or No Deal',
            'Wild Ace',
            'Gold Vault Roulette',
            'Chinese New Year Moreways',
            'Infinite Fun Fun 21 Blackjack',
            'Jin Ji Bao Xi',
            'Lightning Dragon Tiger',
            'Lightning Sic Bo',
            'Lightning Storm',
            'Super Ace Joker',
            'Sugar Bang Bang 2',
            'Prosperity Tree Baccarat',
            'Golden Bank 2',
            'Red Door Roulette',
            'Queen Of Inca',
            'Video Poker',
            'Dragon Link',
            'Slot Machine',
            "Lion's Reward",
            '₱0.1 DuoFuDuoCai',
            'Tian Ci Jin Long',
            'Long Yi Fa',
            'Lion Link',
            '5 Dragons Gold Mystery',
            'Squid Game',
            'All Aboard',
            'XXXtreme Lightning Baccarat',
            'Huff n Even More Puff',
            'Bull Blitz',
            'Super Burst',
            'First Person American Roulette',
            'First Person Baccarat',
            'First Person Dragon Tiger',
            'First Person Dream Catcher',
            'First Person Golden Wealth Baccarat',
            'Color Crush',
            'First Person Lightning Baccarat',
            'First Person Lightning Roulette',
            'Crystal Rush',
            'First Person Roulette',
            'Lucky Fortunes 3x3',
            'First Person Deal or No Deal',
            'First Person Prosperity Tree Baccarat',
            'Chinese New Year 2',
            'First Person XXXtreme Lightning Roulette',
            'War',
            'Lightning Link',
            'Big Fu Cash',
            'Race Track',
            'Mighty Cash',
            'Lucky Ace',
            'Super Win',
            'Treasure Raiders',
            'Dragon Trio',
            'Golden Genie',
            'Super Elements',
            'Mahjong Ways 3+',
            'Sugar Boom',
            'Lucky Panda',
            '777',
            'Candy Gala',
            'Gems Miner',
            'Coin Combo',
            'Zeus',
            '777 Strike',
            'Fruit Shop Megaways',
            'Coin Toss',
            'Plinko',
            'Charge Buffalo',
            'Dazzle Me',
            'Dead Or Alive',
            'Blood Suckers',
            'Butterfly Staxx',
            'Cirque De La Fortune',
            'Golden Tsar',
            'Riddle Of The Sphinx',
            "Dragon's Luck Deluxe",
            'Lucky Oktoberfest',
            'Reel Keeper',
            'Atlantis',
            'Pirates Plenty Battle For Gold',
            'Cash Ultimate',
            "Yucatan's Mystery",
            "Gonzo's Quest Megaways",
            '10,001 Nights',
            'Well Of Wishes',
            'Vault Of Anubis',
            "Da Vinci's Mystery",
            'Jingle Bells Power Reels',
            'Legendary Excalibur',
            'Piggy Riches Megaways',
            'Aztec Spins',
            'Dragon Fire Megaways',
            'Golden Leprechaun Megaways',
            'Thor Vengeance',
            'Legend Of Athena',
            'Clash Of The Beasts',
            'Wings Of Ra',
            'Tower',
            'Three Little Pigs',
            'Night Market',
            'Chinese New Year',
            'Animal Racing',
            'Hot Pot Party',
            'Treasure Cruise',
            'Gold Rush',
            'Robin Hood',
            'Crazy Buffalo',
            'Monkey King Fishing',
            'Bao Chuan Fishing',
            'Fa Chai Fishing',
            'Star Hunter',
            'Money Tree Dozer',
            'Circus Dozer',
            'Lucky 9',
            'Egypt Bonanza',
            'Legend Of Inca',
            'Night Market 2',
            'Roma Gladiatrix',
            'Fish Prawn Crab 2',
            'Dragon Tiger 2',
            'Sic Bo',
            'Tai Xiu',
            'Poker Roulette',
            'Thai Fish Prawn Crab',
            'Xoc Dia Lightning',
            'Fan Tan Classic',
            'Fruit Roulette',
            'European Roulette',
            '7 Up 7 Down',
            'Andar Bahar',
            'Cash Rocket',
            'Bonus Dice',
            'Monkey King Roulette',
            'Tongits',
            'Pusoy',
            'Mega Fishing',
            'Crazy777',
            'Bubble Beauty',
            'Fengshen',
            'Jungle King',
            'Boxing King',
            'Pharaoh Treasure',
            'Agent Ace',
            'Ali Baba',
            'Mayan Empire',
            'JILI Caishen',
            'Bonus Hunter',
            'Chin Shi Huang',
            'Crazy Hunter',
            'Dragon & Tiger',
            'Go Rush',
            'Limbo',
            'Neko Fortune',
            'Poker King',
            'Secret Treasure',
            'Seven Seven Seven',
            'Shanghai Beauty',
            'Teen Patti 20-20',
            'Thor X',
            'Win Drop',
            'Golden Bank',
            'World Cup',
            'Bone Fortune',
            'Lucky Coming',
            'Pirate Queen',
            'Devil Fire 2',
            'Medusa',
            'Magic Lamp',
            'Hot Chilli',
            'Crazy Pusher',
            'Trial of Phoenix',
            'Arena Fighter',
            'Candy Baby',
            'Elf Bingo',
            'Book of Gold',
            'Wild Racer',
            'Golden Queen',
            'War Of Dragons',
            'Golden Joker',
            'Dragon Treasure',
            'Aztec Priestess',
            'King Arthur',
            'Charge Buffalo Ascent',
            'Fortune Monkey',
            'Crazy Hunter 2',
            'Golden Temple',
            'Potion Wizard',
            'Money Pot',
            'Lucky Jaguar',
            'Royal Fishing',
            'Bombing Fishing',
            'Dinosaur Tycoon',
            'Dragon Fortune',
            'Boom Legend',
            'Happy Fishing',
            'All Star Fishing',
            'Ocean King Jackpot',
            'Fortune King Jackpot',
            'Santastic!',
            'Texan Tycoon',
            'Snowmania',
            'Asgard',
            'Lucha Libre',
            "Aztec's Treasure",
            'Megaquarium',
            'Hyper Wins',
            'Fire Dragon',
            'Caesar Empire',
            'Enchanted Garden',
            "Aladdin's Wishes",
            'Gemtopia',
            'Paydirt!',
            'Run Rabbit Run!',
            'Shanghai Lights',
            "Witch's Brew",
            "Cleopatra's Gold",
            'Legend Of Helios',
            'Tiger Treasures',
            "Panda's Gold",
            'Magic Mushroom',
            'Regal Riches',
            'Scuba Fishing',
            'Storm Lords',
            'Fruit Frenzy',
            'Lucky 8',
            'Voodoo Magic',
            'T-Rex',
            'High Fashion',
            'Ghost Ship',
            'Lucky Catch',
            'Tongits Go',
            'Loch Ness Loot',
            'God Of Wealth',
            'Achilles',
            'Nova 7s',
            'Mardi Gras Magic',
            'Princess Warrior',
            'Fruity Bonanza',
            'Trump Card',
            'Mermaid Queen',
            'Big Santa',
            'Lucky Tiger',
            'Diamond Fiesta',
            'Count Spectacular',
            'Purrfect Pets',
            'Miami Jackpots',
            'Fortunate Buddha',
            'Dragon Orb',
            'Pig Winner',
            'Egyptian Gold',
            'Tarot Destiny',
            'Merlin Riches',
            'Sweet Shop Collect',
            'Santa Reel Wheel',
            'Alien Wins',
            'Mermaid Royale',
            'Fortunes Of Olympus',
            'Masks Of Atlantis',
            'Bubble Bubble',
            'Fire Blaze: Blue Wizard',
            'Fire Blaze: Eternal Lady',
            'Fire Blaze: Golden Macaque',
            'Panda Blitz',
            'Panther Moon',
            'Safari Heat',
            'Spindrift',
            'Dragons Gate',
            'Lucky Diamond',
            'Kong',
            'Wonder Elephant',
            'Lantern Wealth',
            'Glamorous Girl',
            'Prosperity Tiger',
            'Fortune Neko',
            'Piggy Bank',
            'Bull Treasure',
            'Dragon Fishing',
            'Dragon Fishing II',
            'Cai Shen Fishing',
            'Dragon Master',
            'Fishing Disco',
            'Fishing Legend',
            'Galaxy Burst',
            'Firework Burst',
            'Mole Crash',
            'Mines2',
            'China Empress',
            'India Treasure',
            "Who's The Boss",
            'The Empire',
            'Double Happiness',
            'Athena',
            '777',
            'Werewolf',
            'Face Off',
            'Halloween',
            'Rich & Happy',
            'Vampire',
            'Moon Festival',
            'Money Meow',
            'Santa Claus',
            'Zuma',
            'Wu Xia',
            'Rat Of Wealth',
            'Fish Prawn Crab',
            'What The Fa',
            'Burlesque 2',
            'Mahjong Ways 3',
            'Caishen Daddy',
            'Mystic Legend 2',
            'Pyramid Of Flames',
            'God Of Fortune',
            'Money Tree',
            'Supreme Ace',
            'Treasures Of Egypt',
            'Opera Panda',
            'Lucky Neko',
            'Give Me Fire',
            "King's Realm",
            'Hammer Of Vulcan',
            'Golden Island',
            'Piggy Master',
            'Cyber Cube',
            'Spaceman',
            'Yeti Quest',
            'Dwarf & Dragon',
            'Gladiator',
            'Mermaid Slingo',
            'Pirate Legends',
            'Diamond Mines',
            'Crazy Color',
            'Red Dragon',
            'Bikini Chaser',
            'Golden Chicken',
            'Prosperity Tree',
            'Sparta Honor',
            'Myth of Phoenix',
            'Songkran Party',
            'Angels & Demons',
            'Zombie Hunter',
            'Cage Fight',
            'Super Tiger Baccarat',
            'Kanto Feast',
            'Plinko+',
            'Chilli Heat Spicy Spins',
            'Zombie Outbreak',
            'Fortune Snake',
        ];
    }
}
