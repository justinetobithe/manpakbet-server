<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\KycSubmission;
use App\Models\Promotion;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AccessControl;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $since = now()->subDays(6)->startOfDay();

        $approved = Transaction::query()->where('status', Transaction::STATUS_APPROVED);

        $daily = Transaction::query()
            ->where('status', Transaction::STATUS_APPROVED)
            ->whereIn('type', [Transaction::TYPE_DEPOSIT, Transaction::TYPE_WITHDRAW])
            ->where('created_at', '>=', $since)
            ->get(['type', 'amount', 'created_at'])
            ->groupBy(fn ($t) => $t->created_at->toDateString());

        $series = collect(CarbonPeriod::create($since, now()))->map(function ($day) use ($daily) {
            $rows = $daily->get($day->toDateString(), collect());

            return [
                'date' => $day->toDateString(),
                'deposits' => (float) $rows->where('type', Transaction::TYPE_DEPOSIT)->sum('amount'),
                'withdrawals' => (float) $rows->where('type', Transaction::TYPE_WITHDRAW)->sum('amount'),
            ];
        })->values();

        return $this->success([
                'totals' => [
                    'players' => User::query()->role(AccessControl::ROLE_USER)->count(),
                    'new_players_today' => User::query()->role(AccessControl::ROLE_USER)->where('created_at', '>=', now()->startOfDay())->count(),
                    'games' => Game::query()->where('is_active', true)->count(),
                    'active_promotions' => Promotion::query()->running()->count(),
                    'pending_transactions' => Transaction::query()->where('status', Transaction::STATUS_PENDING)->count(),
                    'pending_kyc' => KycSubmission::query()->where('status', 'pending')->count(),
                    'total_deposits' => (float) (clone $approved)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount'),
                    'total_withdrawals' => (float) (clone $approved)->where('type', Transaction::TYPE_WITHDRAW)->sum('amount'),
                    'player_balance' => (float) User::query()->sum('balance'),
                ],
                'series' => $series,
                'recent_users' => User::query()->with('roles:id,name')->latest()->limit(6)->get(),
                'recent_transactions' => Transaction::query()->with('user:id,first_name,last_name,email,phone')->latest()->limit(8)->get(),
                'top_games' => Game::query()->with('category:id,name,slug')->orderByDesc('plays_count')->limit(5)->get(['id', 'name', 'slug', 'thumbnail', 'banner', 'plays_count', 'game_category_id']),
        ], 'Dashboard statistics');
    }
}
