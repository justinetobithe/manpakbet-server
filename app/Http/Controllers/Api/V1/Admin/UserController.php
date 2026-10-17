<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Transaction;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\GameStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends ResourceController
{
    protected string $label = 'User';

    protected string $model = User::class;

    protected array $searchable = ['first_name', 'last_name', 'email', 'phone'];

    protected array $sortable = ['id', 'first_name', 'email', 'balance', 'last_login_at', 'created_at'];

    protected array $filterable = ['is_active'];

    protected array $with = ['roles:id,name'];

    protected function query(Request $request): Builder
    {
        $query = parent::query($request);

        if ($role = $request->query('role')) {
            $query->role($role);
        }

        return $query;
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record?->getKey())],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($record?->getKey())],
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['boolean'],
            'password' => [$record ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }

    protected function prepare(Request $request, array $data, ?Model $record = null): array
    {
        $this->guardRoleChange($request, $data['role'], $record);

        unset($data['role']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function guardRoleChange(Request $request, string $role, ?Model $record): void
    {
        $current = $record?->role ?? AccessControl::ROLE_USER;

        if ($role === $current) {
            return;
        }

        if (!$request->user()->can('roles.manage')) {
            $this->fail('You are not allowed to assign roles', 403);
        }

        if ($record && $record->getKey() === $request->user()->getKey() && $current === AccessControl::ROLE_ADMIN) {
            $this->fail('You cannot remove your own admin role');
        }
    }

    protected function afterSave(Request $request, Model $record, array $validated): void
    {
        $record->syncRoles([$validated['role']]);
        $record->load('roles:id,name');
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()->with(['roles:id,name', 'kyc'])->findOrFail($id);
        $approved = $user->transactions()->where('status', Transaction::STATUS_APPROVED);
        $scope = fn ($query) => $query->where('user_id', $user->id);

        return $this->success([
            'user' => array_merge($user->toArray(), ['permissions' => $user->getAllPermissions()->pluck('name')->values()]),
            'stats' => array_merge(
                [
                    'total_deposits' => (float) (clone $approved)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount'),
                    'total_withdrawals' => (float) (clone $approved)->where('type', Transaction::TYPE_WITHDRAW)->sum('amount'),
                    'pending_transactions' => $user->transactions()->where('status', Transaction::STATUS_PENDING)->count(),
                ],
                GameStats::rounds($scope),
                GameStats::sessions($scope),
            ),
        ], 'User retrieved');
    }

    public function destroy(int $id): JsonResponse
    {
        if (request()->user()->getKey() === $id) {
            return $this->error('You cannot delete your own account', 422);
        }

        return parent::destroy($id);
    }

    public function adjustBalance(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-1000000,1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data, $id, $request) {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            $amount = round((float) $data['amount'], 2);

            if ($user->balance + $amount < 0) {
                $this->fail('Balance cannot go below zero', 422, ['amount' => ['Balance cannot go below zero']]);
            }

            $user->balance = $user->balance + $amount;
            $user->save();

            Transaction::query()->create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_ADJUSTMENT,
                'method' => 'manual',
                'amount' => $amount,
                'status' => Transaction::STATUS_APPROVED,
                'notes' => $data['notes'] ?? null,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            return $user;
        });

        return $this->success($user->load('roles:id,name'), 'Balance updated');
    }
}
