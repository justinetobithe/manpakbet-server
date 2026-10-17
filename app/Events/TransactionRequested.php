<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TransactionRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Transaction $transaction)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('backoffice.transactions')];
    }

    public function broadcastAs(): string
    {
        return 'transaction.requested';
    }

    public function broadcastWith(): array
    {
        $user = $this->transaction->user;

        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'reference' => $this->transaction->reference,
                'type' => $this->transaction->type,
                'method' => $this->transaction->method,
                'amount' => (float) $this->transaction->amount,
                'created_at' => $this->transaction->created_at?->toIso8601String(),
            ],
            'user' => $user ? ['id' => $user->id, 'name' => $user->name] : null,
        ];
    }
}
