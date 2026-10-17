<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Support\GameStats;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public const METHODS = ['gcash', 'maya'];

    private function methods(): array
    {
        return array_values(array_filter(self::METHODS, fn ($method) => Settings::get("payments.{$method}_enabled", true)));
    }

    private function channels(): array
    {
        return array_map(fn ($method) => [
            'id' => $method,
            'account_name' => Settings::get("payments.{$method}_account_name"),
            'account_number' => Settings::get("payments.{$method}_account_number"),
        ], $this->methods());
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $approved = $user->transactions()->where('status', Transaction::STATUS_APPROVED);
        $pending = $user->transactions()->where('status', Transaction::STATUS_PENDING);

        return $this->success([
            'balance' => (float) $user->balance,
            'pending_withdrawals' => (float) (clone $pending)->where('type', Transaction::TYPE_WITHDRAW)->sum('amount'),
            'pending_deposits' => (float) (clone $pending)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount'),
            'total_deposits' => (float) (clone $approved)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount'),
            'total_withdrawals' => (float) (clone $approved)->where('type', Transaction::TYPE_WITHDRAW)->sum('amount'),
            'limits' => [
                'min_deposit' => (int) Settings::get('payments.min_deposit'),
                'max_deposit' => (int) Settings::get('payments.max_deposit'),
                'min_withdraw' => (int) Settings::get('payments.min_withdraw'),
                'max_withdraw' => (int) Settings::get('payments.max_withdraw'),
                'methods' => $this->methods(),
            ],
            'channels' => $this->channels(),
            'kyc_status' => $user->kyc_status,
            'require_kyc_for_withdraw' => (bool) Settings::get('payments.require_kyc_for_withdraw'),
        ], 'Wallet retrieved');
    }

    public function transactions(Request $request): JsonResponse
    {
        $query = $request->user()->transactions()->latest('id');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate(min(max((int) $request->query('per_page', 10), 1), 50)), 'Transactions retrieved');
    }

    public function deposit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . Settings::get('payments.min_deposit'), 'max:' . Settings::get('payments.max_deposit')],
            'method' => ['required', Rule::in($this->methods())],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
        ]);

        $transaction = $request->user()->transactions()->create([
            'type' => Transaction::TYPE_DEPOSIT,
            'status' => Transaction::STATUS_PENDING,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'account_name' => $data['account_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
        ]);

        return $this->created($transaction, 'Deposit request submitted');
    }

    public function withdraw(Request $request): JsonResponse
    {
        if (Settings::get('payments.require_kyc_for_withdraw') && !$request->user()->isKycVerified()) {
            return $this->error('Verify your identity before requesting a withdrawal', 403, null, ['code' => 'KYC_REQUIRED']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . Settings::get('payments.min_withdraw'), 'max:' . Settings::get('payments.max_withdraw')],
            'method' => ['required', Rule::in($this->methods())],
            'account_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
        ]);

        $transaction = DB::transaction(function () use ($request, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $amount = round((float) $data['amount'], 2);

            if ($user->balance < $amount) {
                $this->fail('Insufficient balance', 422, ['amount' => ['Insufficient balance']]);
            }

            $user->balance = $user->balance - $amount;
            $user->save();

            return $user->transactions()->create([
                'type' => Transaction::TYPE_WITHDRAW,
                'status' => Transaction::STATUS_PENDING,
                'amount' => $amount,
                'method' => $data['method'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
            ]);
        });

        return $this->created($transaction, 'Withdrawal request submitted');
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $approved = $user->transactions()->where('status', Transaction::STATUS_APPROVED);
        $rounds = GameStats::rounds(fn ($query) => $query->where('user_id', $user->id));
        $cashIn = (float) (clone $approved)->whereIn('type', [Transaction::TYPE_DEPOSIT, Transaction::TYPE_BONUS])->sum('amount');
        $cashOut = (float) (clone $approved)->where('type', Transaction::TYPE_WITHDRAW)->sum('amount');

        return $this->success([
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'total_bets' => $rounds['total_bets'],
            'total_wins' => $rounds['total_wins'],
            'rounds' => $rounds['rounds'],
            'net_gaming' => round($rounds['total_wins'] - $rounds['total_bets'], 2),
            'balance' => (float) $user->balance,
        ], 'History summary retrieved');
    }
}
