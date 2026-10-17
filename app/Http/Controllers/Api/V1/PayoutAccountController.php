<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayoutAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->accounts($request), 'Payout accounts retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->payoutAccounts()->count() >= PayoutAccount::MAX_PER_USER) {
            return $this->error('You can save up to ' . PayoutAccount::MAX_PER_USER . ' payout accounts', 422);
        }

        $request->merge(['account_number' => PayoutAccount::normalizeNumber((string) $request->input('account_number'))]);

        $data = $request->validate([
            'method' => ['required', Rule::in(WalletController::METHODS)],
            'account_name' => ['required', 'string', 'min:2', 'max:100'],
            'account_number' => [
                'required',
                'regex:/^09\d{9}$/',
                Rule::unique('payout_accounts')->where(fn ($query) => $query->where('user_id', $user->id)->where('method', $request->input('method'))),
            ],
            'is_default' => ['sometimes', 'boolean'],
        ], [
            'account_number.regex' => 'Enter a valid 11-digit mobile number starting with 09',
            'account_number.unique' => 'This account is already saved',
        ]);

        $account = DB::transaction(function () use ($user, $data) {
            $makeDefault = ($data['is_default'] ?? false) || !$user->payoutAccounts()->exists();

            if ($makeDefault) {
                $user->payoutAccounts()->update(['is_default' => false]);
            }

            return $user->payoutAccounts()->create([
                'method' => $data['method'],
                'account_name' => trim($data['account_name']),
                'account_number' => $data['account_number'],
                'is_default' => $makeDefault,
            ]);
        });

        return $this->created($account, 'Payout account saved');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $account = $user->payoutAccounts()->findOrFail($id);

        $data = $request->validate([
            'account_name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $account, $data) {
            if (($data['is_default'] ?? false) === true) {
                $user->payoutAccounts()->whereKeyNot($account->id)->update(['is_default' => false]);
                $account->is_default = true;
            }

            if (isset($data['account_name'])) {
                $account->account_name = trim($data['account_name']);
            }

            $account->save();
        });

        return $this->success($account->fresh(), 'Payout account updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $account = $user->payoutAccounts()->findOrFail($id);

        DB::transaction(function () use ($user, $account) {
            $wasDefault = $account->is_default;
            $account->delete();

            if ($wasDefault) {
                $user->payoutAccounts()->latest('id')->first()?->update(['is_default' => true]);
            }
        });

        return $this->success(null, 'Payout account removed');
    }

    private function accounts(Request $request)
    {
        return $request->user()->payoutAccounts()->orderByDesc('is_default')->latest('id')->get();
    }
}
