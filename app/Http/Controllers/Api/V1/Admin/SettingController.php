<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Games\GameProviderManager;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(GameProviderManager $providers): JsonResponse
    {
        return $this->success([
            'settings' => Settings::all(),
            'system' => $this->system($providers),
        ], 'Settings retrieved');
    }

    public function update(Request $request, GameProviderManager $providers): JsonResponse
    {
        $data = $request->validate(Settings::rules());

        if (isset($data['payments']['min_deposit'], $data['payments']['max_deposit']) && $data['payments']['min_deposit'] > $data['payments']['max_deposit']) {
            return $this->error('Minimum deposit cannot exceed the maximum', 422, ['payments.min_deposit' => ['Minimum deposit cannot exceed the maximum']]);
        }

        if (isset($data['payments']['min_withdraw'], $data['payments']['max_withdraw']) && $data['payments']['min_withdraw'] > $data['payments']['max_withdraw']) {
            return $this->error('Minimum withdrawal cannot exceed the maximum', 422, ['payments.min_withdraw' => ['Minimum withdrawal cannot exceed the maximum']]);
        }

        return $this->success([
            'settings' => Settings::update($data),
            'system' => $this->system($providers),
        ], 'Settings saved');
    }

    private function system(GameProviderManager $providers): array
    {
        $driver = $providers->driver();

        return [
            'game_provider' => [
                'driver' => $driver->key(),
                'name' => $driver->name(),
                'connected' => $driver->isConfigured(),
                'callback_url' => url('/api/v1/provider/' . $driver->key() . '/wallet/{action}'),
                'currency' => config('games.currency'),
                'session_ttl_minutes' => (int) config('games.session_ttl_minutes'),
            ],
            'security' => [
                'token_expiration_minutes' => (int) config('sanctum.expiration'),
                'otp_dev_mode' => (bool) config('services.otp.dev_mode'),
            ],
            'app' => [
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ],
        ];
    }
}
