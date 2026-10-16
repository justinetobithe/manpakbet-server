<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends ResourceController
{
    protected string $label = 'Post';

    protected string $model = Post::class;

    protected array $searchable = ['title', 'slug', 'excerpt'];

    protected array $sortable = ['id', 'title', 'published_at', 'created_at'];

    protected array $filterable = ['category', 'is_published', 'is_pinned'];

    protected ?string $slugFrom = 'title';

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('posts', 'slug')->ignore($record?->getKey())],
            'category' => ['required', Rule::in(['news', 'announcement', 'event', 'guide'])],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_pinned' => ['boolean'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepare(Request $request, array $data, ?Model $record = null): array
    {
        $data = parent::prepare($request, $data, $record);

        if (($data['is_published'] ?? false) && empty($data['published_at']) && !$record?->published_at) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
