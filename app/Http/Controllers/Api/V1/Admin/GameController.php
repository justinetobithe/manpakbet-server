<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Game;
use App\Models\GameProvider;
use App\Models\GameRound;
use App\Support\GameStats;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameController extends ResourceController
{
    protected string $label = 'Game';

    protected string $model = Game::class;

    protected array $searchable = ['name', 'slug', 'provider'];

    protected array $sortable = ['id', 'name', 'sort_order', 'plays_count', 'created_at'];

    protected array $filterable = ['game_category_id', 'game_provider_id', 'is_active', 'is_featured', 'badge'];

    protected array $with = ['category:id,name,slug', 'providerRef:id,name,slug'];

    protected ?string $slugFrom = 'name';

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('games', 'slug')->ignore($record?->getKey())],
            'game_category_id' => ['required', 'exists:game_categories,id'],
            'game_provider_id' => ['nullable', 'exists:game_providers,id'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'banner' => ['nullable', 'string', 'max:500'],
            'launch_url' => ['nullable', 'string', 'max:500'],
            'provider_game_code' => ['nullable', 'string', 'max:120'],
            'has_demo' => ['boolean'],
            'badge' => ['nullable', Rule::in(['hot', 'new', 'top', 'jackpot'])],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(Request $request, array $data, ?Model $record = null): array
    {
        $data = parent::prepare($request, $data, $record);

        if (array_key_exists('game_provider_id', $data)) {
            $data['provider'] = $data['game_provider_id'] ? GameProvider::query()->find($data['game_provider_id'])?->name : null;
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    public function show(int $id): JsonResponse
    {
        $game = Game::query()->with(['category:id,name,slug', 'providerRef' => fn ($q) => $q->withCount('games')])->findOrFail($id);
        $scope = fn ($query) => $query->where('game_id', $game->id);

        return $this->success([
            'game' => $game,
            'provider' => $game->providerRef,
            'stats' => array_merge(GameStats::rounds($scope), GameStats::sessions($scope), ['plays' => $game->plays_count]),
            'recent_rounds' => GameRound::query()->with('user:id,first_name,last_name,email,phone')->where('game_id', $game->id)->latest('id')->limit(10)->get(),
        ], 'Game retrieved');
    }
}
