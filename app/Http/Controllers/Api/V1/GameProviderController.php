<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameProvider;
use Illuminate\Http\JsonResponse;

class GameProviderController extends Controller
{
    public function index(): JsonResponse
    {
        $providers = GameProvider::query()
            ->where('is_active', true)
            ->withCount(['games' => fn ($g) => $g->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success($providers, 'Providers retrieved');
    }
}
