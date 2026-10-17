<?php

namespace App\Events;

use App\Models\PayoutAccount;
use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TransactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Transaction $transaction, public float $balance)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->transaction->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'transaction.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'reference' => $this->transaction->reference,
                'type' => $this->transaction->type,
                'method' => $this->transaction->method,
                'status' => $this->transaction->status,
                'amount' => (float) $this->transaction->amount,
                'account_number' => PayoutAccount::mask($this->transaction->account_number),
                'notes' => $this->transaction->notes,
            ],
            'balance' => $this->balance,
        ];
    }
}
