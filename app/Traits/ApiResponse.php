<?php

namespace App\Traits;

use App\Support\ApiResponder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $code = 200, array $extra = []): JsonResponse
    {
        return ApiResponder::success($data, $message, $code, $extra);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return ApiResponder::success($data, $message, 201);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $message = 'OK', array $extra = []): JsonResponse
    {
        return ApiResponder::paginated($paginator, $message, $extra);
    }

    protected function error(string $message, int $code = 400, ?array $errors = null, mixed $data = null): JsonResponse
    {
        return ApiResponder::error($message, $code, $errors, $data);
    }

    protected function fail(string $message, int $code = 422, ?array $errors = null): never
    {
        ApiResponder::fail($message, $code, $errors);
    }
}
