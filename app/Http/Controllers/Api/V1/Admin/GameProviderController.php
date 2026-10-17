<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\GameProvider;
use App\Support\GameStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameProviderController extends ResourceController
{
    protected string $label = 'Provider';

    protected string $model = GameProvider::class;

    protected array $searchable = ['name', 'slug'];

    protected array $sortable = ['id', 'name', 'sort_order', 'created_at'];

    protected array $filterable = ['is_active'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    protected ?string $slugFrom = 'name';

    protected function query(Request $request): Builder
    {
        return parent::query($request)->withCount('games');
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('game_providers', 'slug')->ignore($record?->getKey())],
            'logo' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(Request $request, array $data, ?Model $record = null): array
    {
        $data = parent::prepare($request, $data, $record);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    public function show(int $id): JsonResponse
    {
        $provider = GameProvider::query()
            ->withCount(['games', 'games as active_games_count' => fn ($q) => $q->where('is_active', true)])
            ->findOrFail($id);

        $gameIds = $provider->games()->pluck('id');
        $scope = fn ($query) => $query->whereIn('game_id', $gameIds);

        return $this->success([
            'provider' => $provider,
            'stats' => array_merge(GameStats::rounds($scope), GameStats::sessions($scope), [
                'plays' => (int) $provider->games()->sum('plays_count'),
            ]),
            'top_games' => $provider->games()->with('category:id,name,slug')->orderByDesc('plays_count')->limit(12)->get(),
        ], 'Provider retrieved');
    }
}
