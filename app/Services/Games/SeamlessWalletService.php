<?php

namespace App\Services\Games;

use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SeamlessWalletService
{
    public function session(string $driver, ?string $token): GameSession
    {
        $session = $token ? GameSession::query()->where('driver', $driver)->where('token', $token)->first() : null;

        if (!$session) {
            throw new GameProviderException('Invalid session token', 'INVALID_TOKEN', 401);
        }

        if ($session->mode !== GameSession::MODE_REAL) {
            throw new GameProviderException('Demo sessions do not use the real wallet', 'DEMO_SESSION', 403);
        }

        if (!$session->user->is_active) {
            throw new GameProviderException('Player account is suspended', 'PLAYER_SUSPENDED', 403);
        }

        return $session;
    }

    public function balance(GameSession $session): array
    {
        return $this->result((float) $session->user->fresh()->balance);
    }

    public function debit(GameSession $session, string $externalId, ?string $roundId, float $amount, array $payload): array
    {
        if (!$session->isActive()) {
            throw new GameProviderException('Game session has ended', 'SESSION_EXPIRED', 401);
        }

        return $this->apply($session, GameRound::TYPE_BET, $externalId, $roundId, -$amount, $payload);
    }

    public function credit(GameSession $session, string $externalId, ?string $roundId, float $amount, array $payload): array
    {
        return $this->apply($session, GameRound::TYPE_WIN, $externalId, $roundId, $amount, $payload);
    }

    public function rollback(GameSession $session, string $externalId, string $referenceId, array $payload): array
    {
        return DB::transaction(function () use ($session, $externalId, $referenceId, $payload) {
            if ($existing = $this->find($session->driver, $externalId)) {
                return $this->result((float) $existing->balance_after, $existing->id, true);
            }

            $user = User::query()->lockForUpdate()->findOrFail($session->user_id);
            $original = GameRound::query()
                ->where('driver', $session->driver)
                ->where('external_id', $referenceId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $before = (float) $user->balance;
            $delta = 0.0;

            if ($original && $original->status === GameRound::STATUS_COMPLETED) {
                $delta = $original->type === GameRound::TYPE_BET ? (float) $original->amount : -(float) $original->amount;

                if ($before + $delta < 0) {
                    throw new GameProviderException('Insufficient balance to roll back', 'INSUFFICIENT_FUNDS', 409);
                }

                $original->update(['status' => GameRound::STATUS_ROLLED_BACK]);
            }

            $user->balance = round($before + $delta, 2);
            $user->save();

            $round = GameRound::query()->create([
                'game_session_id' => $session->id,
                'user_id' => $user->id,
                'game_id' => $session->game_id,
                'driver' => $session->driver,
                'external_id' => $externalId,
                'round_id' => $original?->round_id,
                'type' => GameRound::TYPE_REFUND,
                'amount' => abs($delta),
                'balance_before' => $before,
                'balance_after' => $user->balance,
                'reference_external_id' => $referenceId,
                'payload' => $payload,
            ]);

            return $this->result((float) $user->balance, $round->id);
        });
    }

    private function apply(GameSession $session, string $type, string $externalId, ?string $roundId, float $delta, array $payload): array
    {
        if (abs($delta) <= 0 && $type === GameRound::TYPE_BET) {
            throw new GameProviderException('Bet amount must be greater than zero', 'INVALID_AMOUNT');
        }

        return DB::transaction(function () use ($session, $type, $externalId, $roundId, $delta, $payload) {
            if ($existing = $this->find($session->driver, $externalId)) {
                return $this->result((float) $existing->balance_after, $existing->id, true);
            }

            $user = User::query()->lockForUpdate()->findOrFail($session->user_id);
            $before = (float) $user->balance;

            if ($before + $delta < 0) {
                throw new GameProviderException('Insufficient balance', 'INSUFFICIENT_FUNDS', 409);
            }

            $user->balance = round($before + $delta, 2);
            $user->save();

            $round = GameRound::query()->create([
                'game_session_id' => $session->id,
                'user_id' => $user->id,
                'game_id' => $session->game_id,
                'driver' => $session->driver,
                'external_id' => $externalId,
                'round_id' => $roundId,
                'type' => $type,
                'amount' => abs($delta),
                'balance_before' => $before,
                'balance_after' => $user->balance,
                'payload' => $payload,
            ]);

            return $this->result((float) $user->balance, $round->id);
        });
    }

    private function find(string $driver, string $externalId): ?GameRound
    {
        return GameRound::query()->where('driver', $driver)->where('external_id', $externalId)->first();
    }

    private function result(float $balance, ?int $transactionId = null, bool $duplicate = false): array
    {
        return array_filter([
            'balance' => round($balance, 2),
            'currency' => config('games.currency'),
            'transaction_id' => $transactionId,
            'duplicate' => $duplicate ?: null,
        ], fn ($value) => $value !== null);
    }
}
