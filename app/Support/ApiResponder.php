<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class ApiResponder
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    public static function success(mixed $data = null, string $message = 'OK', int $code = 200, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => self::STATUS_SUCCESS,
            'message' => $message,
            'data' => $data,
        ], $extra), $code);
    }

    public static function paginated(LengthAwarePaginator $paginator, string $message = 'OK', array $extra = []): JsonResponse
    {
        return self::success($paginator->items(), $message, 200, array_merge([
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $extra));
    }

    public static function error(string $message, int $code = 400, ?array $errors = null, mixed $data = null): JsonResponse
    {
        $payload = [
            'status' => self::STATUS_ERROR,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    public static function fail(string $message, int $code = 422, ?array $errors = null): never
    {
        throw new HttpResponseException(self::error($message, $code, $errors));
    }
}
