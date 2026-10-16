<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function launch(Request $request, string $slug): JsonResponse
    {
        $game = Game::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $game->increment('plays_count');

        return $this->success([
            'game' => $game,
            'launch_url' => $game->launch_url,
            'balance' => (float) $request->user()->balance,
        ], 'Game session ready');
    }
}
