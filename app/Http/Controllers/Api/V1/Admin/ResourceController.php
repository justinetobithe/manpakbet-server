<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class ResourceController extends Controller
{
    protected string $model;

    protected string $label = 'Record';

    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'created_at'];

    protected array $filterable = [];

    protected array $with = [];

    protected string $defaultSort = 'id';

    protected string $defaultDirection = 'desc';

    protected ?string $slugFrom = null;

    abstract protected function rules(Request $request, ?Model $record = null): array;

    protected function query(Request $request): Builder
    {
        return $this->model::query()->with($this->with);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ($this->filterable as $column) {
            $value = $request->query($column);
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        $sort = in_array($request->query('sort'), $this->sortable, true) ? $request->query('sort') : $this->defaultSort;
        $direction = in_array($request->query('direction'), ['asc', 'desc'], true) ? $request->query('direction') : $this->defaultDirection;
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return $this->paginated($query->orderBy($sort, $direction)->paginate($perPage), Str::plural($this->label) . ' retrieved');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->model::query()->with($this->with)->findOrFail($id), "{$this->label} retrieved");
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));

        $record = $this->model::query()->create($this->prepare($request, $validated));

        $this->afterSave($request, $record, $validated);

        return $this->created($record->load($this->with), "{$this->label} created successfully");
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->model::query()->findOrFail($id);

        $validated = $request->validate($this->rules($request, $record));

        $record->update($this->prepare($request, $validated, $record));

        $this->afterSave($request, $record, $validated);

        return $this->success($record->fresh($this->with), "{$this->label} updated successfully");
    }

    public function destroy(int $id): JsonResponse
    {
        $this->model::query()->findOrFail($id)->delete();

        return $this->success(null, "{$this->label} deleted successfully");
    }

    protected function prepare(Request $request, array $data, ?Model $record = null): array
    {
        if ($this->slugFrom && empty($data['slug']) && !empty($data[$this->slugFrom])) {
            $data['slug'] = $this->uniqueSlug($data[$this->slugFrom], $record?->getKey());
        }

        return $data;
    }

    protected function afterSave(Request $request, Model $record, array $validated): void
    {
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(6));
        $slug = $base;
        $i = 2;

        while ($this->model::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
