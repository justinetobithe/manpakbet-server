<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Games\GameProviderException;
use App\Services\Games\GameProviderManager;
use App\Services\Games\SeamlessWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderWalletController extends Controller
{
    public function __construct(
        private readonly GameProviderManager $providers,
        private readonly SeamlessWalletService $wallet,
    ) {
    }

    public function __invoke(Request $request, string $driver, string $action): JsonResponse
    {
        try {
            $provider = $this->providers->driver($driver);

            if (!$provider->verifyCallback($request)) {
                throw new GameProviderException('Invalid signature', 'INVALID_SIGNATURE', 401);
            }

            $data = $request->validate([
                'token' => ['required', 'string'],
                'transaction_id' => [in_array($action, ['debit', 'credit', 'rollback'], true) ? 'required' : 'nullable', 'string', 'max:120'],
                'round_id' => ['nullable', 'string', 'max:120'],
                'amount' => [in_array($action, ['debit', 'credit'], true) ? 'required' : 'nullable', 'numeric', 'min:0', 'max:10000000'],
                'reference_transaction_id' => [$action === 'rollback' ? 'required' : 'nullable', 'string', 'max:120'],
            ]);

            $session = $this->wallet->session($provider->key(), $data['token']);
            $payload = $request->except(['token']);

            $result = match ($action) {
                'balance' => $this->wallet->balance($session),
                'debit' => $this->wallet->debit($session, $data['transaction_id'], $data['round_id'] ?? null, (float) $data['amount'], $payload),
                'credit' => $this->wallet->credit($session, $data['transaction_id'], $data['round_id'] ?? null, (float) $data['amount'], $payload),
                'rollback' => $this->wallet->rollback($session, $data['transaction_id'], $data['reference_transaction_id'], $payload),
                default => throw new GameProviderException('Unknown wallet action', 'UNKNOWN_ACTION', 404),
            };

            return $this->success($result, 'OK');
        } catch (GameProviderException $e) {
            Log::info('Provider wallet callback rejected', ['driver' => $driver, 'action' => $action, 'code' => $e->errorCode()]);

            return $this->error($e->getMessage(), $e->status(), null, ['code' => $e->errorCode()]);
        }
    }
}
