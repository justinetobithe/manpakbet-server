<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BannerController extends ResourceController
{
    protected string $label = 'Banner';

    protected string $model = Banner::class;

    protected array $searchable = ['title', 'subtitle'];

    protected array $sortable = ['id', 'title', 'sort_order', 'created_at'];

    protected array $filterable = ['placement', 'is_active'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'string', 'max:500'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:50'],
            'placement' => ['required', Rule::in(['hero', 'side', 'promo'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(Request $request, array $data, ?Model $record = null): array
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
