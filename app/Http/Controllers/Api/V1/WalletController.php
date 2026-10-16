<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public const METHODS = ['gcash', 'maya'];

    public const MIN_DEPOSIT = 100;

    public const MIN_WITHDRAW = 200;

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
                'min_deposit' => self::MIN_DEPOSIT,
                'min_withdraw' => self::MIN_WITHDRAW,
                'methods' => self::METHODS,
            ],
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
            'amount' => ['required', 'numeric', 'min:' . self::MIN_DEPOSIT, 'max:500000'],
            'method' => ['required', Rule::in(self::METHODS)],
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
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . self::MIN_WITHDRAW, 'max:500000'],
            'method' => ['required', Rule::in(self::METHODS)],
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
}
