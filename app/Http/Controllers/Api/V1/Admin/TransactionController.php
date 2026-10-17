<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\TransactionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    private const RELATIONS = ['user:id,first_name,last_name,email,phone', 'processor:id,first_name,last_name,email'];

    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query()->with(self::RELATIONS);

        foreach (['type', 'status', 'method', 'user_id'] as $column) {
            if ($value = $request->query($column)) {
                $query->where($column, $value);
            }
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $sort = in_array($request->query('sort'), ['id', 'amount', 'created_at', 'processed_at'], true) ? $request->query('sort') : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return $this->paginated($query->orderBy($sort, $direction)->paginate($perPage), 'Transactions retrieved');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $transaction = DB::transaction(function () use ($request, $id) {
            $transaction = $this->lockPending($id);

            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                $user = User::query()->lockForUpdate()->findOrFail($transaction->user_id);
                $user->balance = $user->balance + $transaction->amount;
                $user->save();
            }

            return $this->finalize($transaction, Transaction::STATUS_APPROVED, $request);
        });

        $this->notify($transaction);

        return $this->success($transaction, 'Transaction approved');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:255']]);

        $transaction = DB::transaction(function () use ($request, $id) {
            $transaction = $this->lockPending($id);

            if ($transaction->type === Transaction::TYPE_WITHDRAW) {
                $user = User::query()->lockForUpdate()->findOrFail($transaction->user_id);
                $user->balance = $user->balance + $transaction->amount;
                $user->save();
            }

            return $this->finalize($transaction, Transaction::STATUS_REJECTED, $request);
        });

        $this->notify($transaction);

        return $this->success($transaction, 'Transaction rejected');
    }

    private function lockPending(int $id): Transaction
    {
        $transaction = Transaction::query()->lockForUpdate()->findOrFail($id);

        if ($transaction->status !== Transaction::STATUS_PENDING) {
            $this->fail('Transaction already processed');
        }

        return $transaction;
    }

    private function finalize(Transaction $transaction, string $status, Request $request): Transaction
    {
        $transaction->status = $status;
        $transaction->processed_by = $request->user()->id;
        $transaction->processed_at = now();

        if ($request->filled('notes')) {
            $transaction->notes = $request->input('notes');
        }

        $transaction->save();

        return $transaction->load(self::RELATIONS);
    }

    private function notify(Transaction $transaction): void
    {
        Realtime::dispatch(new TransactionUpdated($transaction, (float) User::query()->whereKey($transaction->user_id)->value('balance')));
    }
}
