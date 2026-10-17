<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameRoundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GameRound::query()->with(['game:id,name,slug,thumbnail,banner', 'user:id,first_name,last_name,email,phone']);

        foreach (['user_id', 'game_id', 'type', 'status'] as $column) {
            if ($value = $request->query($column)) {
                $query->where($column, $value);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return $this->paginated($query->latest('id')->paginate($perPage), 'Game rounds retrieved');
    }
}
