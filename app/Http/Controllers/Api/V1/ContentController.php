<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Post;
use App\Models\Promotion;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function settings(): JsonResponse
    {
        return $this->success(Settings::public(), 'Settings retrieved');
    }

    public function banners(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->where('is_active', true)
            ->when($request->query('placement'), fn ($q, $placement) => $q->where('placement', $placement))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return $this->success($banners, 'Banners retrieved');
    }

    public function promotions(Request $request): JsonResponse
    {
        $promotions = Promotion::query()
            ->running()
            ->when($request->query('tag'), fn ($q, $tag) => $q->where('tag', $tag))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return $this->success($promotions, 'Promotions retrieved');
    }

    public function promotion(string $slug): JsonResponse
    {
        return $this->success(Promotion::query()->running()->where('slug', $slug)->firstOrFail(), 'Promotion retrieved');
    }

    public function posts(Request $request): JsonResponse
    {
        $paginator = Post::query()
            ->published()
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(min(max((int) $request->query('per_page', 12), 1), 50));

        return $this->paginated($paginator, 'Posts retrieved');
    }

    public function post(string $slug): JsonResponse
    {
        return $this->success(Post::query()->published()->where('slug', $slug)->firstOrFail(), 'Post retrieved');
    }
}
