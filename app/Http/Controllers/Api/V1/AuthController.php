<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessControl;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
        ]);

        $user = User::query()->create([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => isset($data['phone']) ? $this->normalizePhone($data['phone']) : null,
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole(AccessControl::ROLE_USER);

        return $this->issueToken($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->error('Invalid email or password', 422);
        }

        return $this->issueToken($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out');
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        $code = (string) random_int(100000, 999999);

        DB::table('otp_codes')->where('phone', $phone)->delete();

        DB::table('otp_codes')->insert([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes((int) config('services.otp.expires_minutes', 5)),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success(
            ['expires_in' => (int) config('services.otp.expires_minutes', 5) * 60, 'dev_code' => config('services.otp.dev_mode') ? $code : null],
            'Verification code sent'
        );
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        $row = DB::table('otp_codes')->where('phone', $phone)->first();

        if (!$row) {
            return $this->error('Request a verification code first', 422);
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            DB::table('otp_codes')->where('phone', $phone)->delete();
            return $this->error('Verification code expired', 422);
        }

        if ((int) $row->attempts >= 5) {
            return $this->error('Too many attempts, request a new code', 429);
        }

        DB::table('otp_codes')->where('phone', $phone)->update([
            'attempts' => ((int) $row->attempts) + 1,
            'updated_at' => now(),
        ]);

        if (!Hash::check($data['code'], $row->code_hash)) {
            return $this->error('Invalid verification code', 422);
        }

        DB::table('otp_codes')->where('phone', $phone)->delete();

        $user = User::query()->where('phone', $phone)->first();

        if (!$user) {
            $user = User::query()->create([
                'phone' => $phone,
                'phone_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->assignRole(AccessControl::ROLE_USER);
        } elseif (!$user->phone_verified_at) {
            $user->phone_verified_at = now();
            $user->save();
        }

        return $this->issueToken($user);
    }

    public function google(Request $request): JsonResponse
    {
        $data = $request->validate(['access_provider_token' => ['required', 'string']]);

        $profile = $this->fetchJson('https://openidconnect.googleapis.com/v1/userinfo', $data['access_provider_token']);

        if (!$profile || empty($profile['sub'])) {
            return $this->error('Invalid Google token', 422);
        }

        $user = $this->upsertSocialUser('google', (string) $profile['sub'], $profile['name'] ?? null, $profile['email'] ?? null);

        return $this->issueToken($user);
    }

    public function facebook(Request $request): JsonResponse
    {
        $data = $request->validate(['access_provider_token' => ['required', 'string']]);

        $profile = $this->fetchJson('https://graph.facebook.com/me?fields=id,name,email', $data['access_provider_token']);

        if (!$profile || empty($profile['id'])) {
            return $this->error('Invalid Facebook token', 422);
        }

        $user = $this->upsertSocialUser('facebook', (string) $profile['id'], $profile['name'] ?? null, $profile['email'] ?? null);

        return $this->issueToken($user);
    }

    private function issueToken(User $user, int $status = 200): JsonResponse
    {
        if (!$user->is_active) {
            return $this->error('This account has been suspended', 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $minutes > 0 ? now()->addMinutes($minutes) : null;

        return $this->success([
            'token' => $user->createToken('api', ['*'], $expiresAt)->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => $user->fresh()->toAuthArray(),
        ], $status === 201 ? 'Account created successfully' : 'Signed in successfully', $status);
    }

    private function fetchJson(string $url, string $token): ?array
    {
        try {
            $response = Http::timeout(10)->withToken($token)->acceptJson()->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    private function upsertSocialUser(string $provider, string $providerId, ?string $name, ?string $email): User
    {
        $user = User::query()->where('provider', $provider)->where('provider_id', $providerId)->first();

        if ($user) {
            return $user;
        }

        [$first, $last] = $this->splitName($name);

        if ($email && ($existing = User::query()->where('email', $email)->first())) {
            $existing->provider = $provider;
            $existing->provider_id = $providerId;
            $existing->first_name = $existing->first_name ?: $first;
            $existing->last_name = $existing->last_name ?: $last;
            $existing->save();

            return $existing;
        }

        $user = User::query()->create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'provider' => $provider,
            'provider_id' => $providerId,
            'password' => Hash::make(Str::random(40)),
        ]);

        $user->assignRole(AccessControl::ROLE_USER);

        return $user;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '63' . substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '63' . $digits;
        }

        return $digits;
    }

    private function splitName(?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name);

        return [$parts[0], count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null];
    }
}
