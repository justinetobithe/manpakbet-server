<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Settings
{
    private const CACHE_KEY = 'app.settings';

    public const SCHEMA = [
        'general' => [
            'site_name' => ['type' => 'string', 'default' => 'ManpakBet', 'rules' => ['required', 'string', 'max:60'], 'public' => true],
            'tagline' => ['type' => 'string', 'default' => 'Play bold. Win brighter.', 'rules' => ['nullable', 'string', 'max:120'], 'public' => true],
            'support_email' => ['type' => 'string', 'default' => 'support@manpakbet.com', 'rules' => ['nullable', 'email', 'max:120'], 'public' => true],
            'support_phone' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:30'], 'public' => true],
            'announcement' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255'], 'public' => true],
            'maintenance_mode' => ['type' => 'bool', 'default' => false, 'rules' => ['boolean'], 'public' => true],
        ],
        'payments' => [
            'gcash_enabled' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean'], 'public' => true],
            'gcash_account_name' => ['type' => 'string', 'default' => 'ManpakBet Payments', 'rules' => ['nullable', 'string', 'max:100'], 'public' => true],
            'gcash_account_number' => ['type' => 'string', 'default' => '09276192326', 'rules' => ['nullable', 'string', 'max:30'], 'public' => true],
            'gcash_qr' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255'], 'public' => true],
            'maya_enabled' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean'], 'public' => true],
            'maya_account_name' => ['type' => 'string', 'default' => 'ManpakBet Payments', 'rules' => ['nullable', 'string', 'max:100'], 'public' => true],
            'maya_account_number' => ['type' => 'string', 'default' => '0918 000 0000', 'rules' => ['nullable', 'string', 'max:30'], 'public' => true],
            'maya_qr' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255'], 'public' => true],
            'require_kyc_for_withdraw' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean'], 'public' => true],
            'min_deposit' => ['type' => 'int', 'default' => 100, 'rules' => ['required', 'integer', 'min:1', 'max:1000000'], 'public' => true],
            'max_deposit' => ['type' => 'int', 'default' => 500000, 'rules' => ['required', 'integer', 'min:1', 'max:10000000'], 'public' => true],
            'min_withdraw' => ['type' => 'int', 'default' => 200, 'rules' => ['required', 'integer', 'min:1', 'max:1000000'], 'public' => true],
            'max_withdraw' => ['type' => 'int', 'default' => 500000, 'rules' => ['required', 'integer', 'min:1', 'max:10000000'], 'public' => true],
        ],
        'games' => [
            'demo_enabled' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean'], 'public' => true],
            'hot_badge_limit' => ['type' => 'int', 'default' => 12, 'rules' => ['required', 'integer', 'min:1', 'max:60'], 'public' => false],
        ],
    ];

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (!Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });

        $values = [];

        foreach (self::SCHEMA as $group => $fields) {
            foreach ($fields as $key => $definition) {
                $values[$group][$key] = self::cast($definition['type'], $stored["{$group}.{$key}"] ?? $definition['default']);
            }
        }

        return $values;
    }

    public static function public(): array
    {
        $all = self::all();
        $public = [];

        foreach (self::SCHEMA as $group => $fields) {
            foreach ($fields as $key => $definition) {
                if ($definition['public']) {
                    $public[$group][$key] = $all[$group][$key];
                }
            }
        }

        return $public;
    }

    public static function get(string $path, mixed $fallback = null): mixed
    {
        return data_get(self::all(), $path, $fallback);
    }

    public static function rules(): array
    {
        $rules = [];

        foreach (self::SCHEMA as $group => $fields) {
            foreach ($fields as $key => $definition) {
                $rules["{$group}.{$key}"] = array_merge(['sometimes'], $definition['rules']);
            }
        }

        return $rules;
    }

    public static function update(array $input): array
    {
        foreach (self::SCHEMA as $group => $fields) {
            foreach ($fields as $key => $definition) {
                if (array_key_exists($group, $input) && array_key_exists($key, (array) $input[$group])) {
                    Setting::query()->updateOrCreate(
                        ['key' => "{$group}.{$key}"],
                        ['value' => self::cast($definition['type'], $input[$group][$key])]
                    );
                }
            }
        }

        Cache::forget(self::CACHE_KEY);

        return self::all();
    }

    private static function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            default => $value === null ? '' : (string) $value,
        };
    }
}
