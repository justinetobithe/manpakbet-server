<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        $code = (string) random_int(100000, 999999);

        $expiresMinutes = (int) env('OTP_EXPIRES_MINUTES', 5);
        $expiresAt = Carbon::now()->addMinutes($expiresMinutes);

        DB::table('otp_codes')->where('phone', $phone)->delete();

        DB::table('otp_codes')->insert([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (filter_var(env('OTP_DEV_MODE', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['ok' => true, 'dev_code' => $code]);
        }

        return response()->json(['ok' => true]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        $code = $data['code'];

        $row = DB::table('otp_codes')->where('phone', $phone)->first();

        if (!$row) return response()->json(['message' => 'OTP not found'], 422);

        if (Carbon::parse($row->expires_at)->isPast()) {
            DB::table('otp_codes')->where('phone', $phone)->delete();
            return response()->json(['message' => 'OTP expired'], 422);
        }

        if ((int) $row->attempts >= 5) {
            return response()->json(['message' => 'Too many attempts'], 429);
        }

        $ok = Hash::check($code, $row->code_hash);

        DB::table('otp_codes')->where('phone', $phone)->update([
            'attempts' => ((int) $row->attempts) + 1,
            'updated_at' => now(),
        ]);

        if (!$ok) return response()->json(['message' => 'Invalid OTP'], 422);

        DB::table('otp_codes')->where('phone', $phone)->delete();

        $user = User::query()->where('phone', $phone)->first();

        if (!$user) {
            $user = User::query()->create([
                'name' => 'User ' . substr($phone, -4),
                'email' => null,
                'phone' => $phone,
                'phone_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
            ]);
        } else {
            if (!$user->phone_verified_at) {
                $user->phone_verified_at = now();
                $user->save();
            }
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'user' => $user]);
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        $su = Socialite::driver('google')->stateless()->user();
        $user = $this->upsertSocialUser('google', $su->getId(), $su->getName(), $su->getEmail());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        return redirect(env('FRONTEND_URL') . '/auth/success');
    }

    public function facebookRedirect()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function facebookCallback(Request $request)
    {
        $su = Socialite::driver('facebook')->stateless()->user();
        $user = $this->upsertSocialUser('facebook', $su->getId(), $su->getName(), $su->getEmail());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        return redirect(env('FRONTEND_URL') . '/auth/success');
    }

    private function upsertSocialUser(string $provider, string $providerId, ?string $name, ?string $email): User
    {
        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user) return $user;

        if ($email) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing) {
                $existing->provider = $provider;
                $existing->provider_id = $providerId;
                $existing->save();
                return $existing;
            }
        }

        return User::query()->create([
            'name' => $name ?: ucfirst($provider) . ' User',
            'email' => $email,
            'provider' => $provider,
            'provider_id' => $providerId,
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', $phone);
    }
}
