<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GameCategory::query()
            ->where('is_active', true)
            ->withCount(['games' => fn ($g) => $g->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name');

        if (!$request->boolean('with_games')) {
            return $this->success($query->get(), 'Categories retrieved');
        }

        $limit = min(max((int) $request->query('games_limit', 12), 1), 48);

        $categories = $query->get()->each(function (GameCategory $category) use ($limit) {
            $category->setRelation('games', $category->games()
                ->where('is_active', true)
                ->with('providerRef:id,name,slug')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit($limit)
                ->get());
        });

        return $this->success($categories, 'Categories retrieved');
    }

    public function show(string $slug): JsonResponse
    {
        $category = GameCategory::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->withCount(['games' => fn ($g) => $g->where('is_active', true)])
            ->firstOrFail();

        return $this->success($category, 'Category retrieved');
    }
}
