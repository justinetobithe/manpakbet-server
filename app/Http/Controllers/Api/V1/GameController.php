<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameRound;
use App\Models\GameSession;
use App\Services\Games\GameLaunchService;
use App\Services\Games\GameProviderException;
use App\Services\Games\GameProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    private const RELATIONS = ['category:id,name,slug', 'providerRef:id,name,slug,logo'];

    public function index(Request $request): JsonResponse
    {
        $query = Game::query()->with(self::RELATIONS)->where('is_active', true);

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $category));
        }

        if ($provider = $request->query('provider')) {
            $query->whereHas('providerRef', fn ($p) => $p->where('slug', $provider));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($badge = $request->query('badge')) {
            $query->where('badge', $badge);
        }

        if ($slugs = $request->query('slugs')) {
            $query->whereIn('slug', array_slice(array_filter(explode(',', $slugs)), 0, 60));
        }

        match ($request->query('sort')) {
            'popular' => $query->orderByDesc('plays_count'),
            'newest' => $query->orderByDesc('id'),
            'name' => $query->orderBy('name'),
            default => $query->orderBy('sort_order')->orderBy('name'),
        };

        return $this->paginated($query->paginate(min(max((int) $request->query('per_page', 24), 1), 120)), 'Games retrieved');
    }

    public function show(string $slug): JsonResponse
    {
        $game = Game::query()->with(self::RELATIONS)->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $related = Game::query()
            ->with(self::RELATIONS)
            ->where('is_active', true)
            ->where('game_category_id', $game->game_category_id)
            ->whereKeyNot($game->id)
            ->orderByDesc('plays_count')
            ->limit(12)
            ->get();

        return $this->success($game, 'Game retrieved', 200, ['related' => $related]);
    }

    public function launch(Request $request, string $slug, GameLaunchService $launcher, GameProviderManager $providers): JsonResponse
    {
        $data = $request->validate(['mode' => ['nullable', Rule::in([GameSession::MODE_REAL, GameSession::MODE_DEMO])]]);
        $game = Game::query()->with(self::RELATIONS)->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $user = $request->user();
        $mode = $data['mode'] ?? GameSession::MODE_REAL;

        if ($mode === GameSession::MODE_REAL && (float) $user->balance <= 0) {
            return $this->error('Top up your wallet to play for real, or try the demo.', 422, null, ['code' => 'INSUFFICIENT_FUNDS']);
        }

        try {
            $session = $launcher->launch($user, $game, $mode, $request);
        } catch (GameProviderException $e) {
            return $this->error($e->getMessage(), $e->status(), null, ['code' => $e->errorCode()]);
        }

        $driver = $providers->driver($session->driver);

        return $this->success([
            'session' => $session->only(['token', 'mode', 'expires_at']),
            'game' => $game,
            'launch_url' => $session->launch_url,
            'provider' => ['key' => $driver->key(), 'name' => $driver->name(), 'connected' => $driver->isConfigured()],
            'balance' => (float) $user->fresh()->balance,
        ], $session->launch_url ? 'Game session ready' : 'Game provider is not connected yet');
    }

    public function endSession(Request $request, GameLaunchService $launcher): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        $launcher->end($request->user(), $data['token']);

        return $this->success(null, 'Game session ended');
    }

    public function rounds(Request $request): JsonResponse
    {
        $paginator = GameRound::query()
            ->with('game:id,name,slug,thumbnail,banner')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        return $this->paginated($paginator, 'Game history retrieved');
    }
}
