<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends ResourceController
{
    protected string $label = 'Promotion';

    protected string $model = Promotion::class;

    protected array $searchable = ['title', 'slug', 'tag'];

    protected array $sortable = ['id', 'title', 'sort_order', 'starts_at', 'ends_at', 'created_at'];

    protected array $filterable = ['is_active', 'tag'];

    protected ?string $slugFrom = 'title';

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('promotions', 'slug')->ignore($record?->getKey())],
            'tag' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:50'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
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
}
