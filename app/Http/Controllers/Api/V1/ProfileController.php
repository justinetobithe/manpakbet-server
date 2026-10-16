<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success($request->user()->toAuthArray(), 'Profile retrieved');
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        $user->update($data);

        return $this->success($user->fresh()->toAuthArray(), 'Profile updated');
    }

    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed', 'different:current_password'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return $this->error('Current password is incorrect', 422, ['current_password' => ['Current password is incorrect']]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return $this->success(null, 'Password updated');
    }
}
