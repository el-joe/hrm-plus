<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The one JSON envelope of /api/v1.
 *
 * Success:  {"success": true,  "message": string|null, "data": mixed, "meta": object?}
 * Failure:  {"success": false, "message": string, "code": string, "errors": {field: [messages]}?}
 *
 * Paginated lists put the items in "data" and add
 * "meta": {"pagination": {"current_page", "per_page", "total", "last_page"}}.
 */
class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve();
        }

        $payload = ['success' => true, 'message' => $message, 'data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resourceClass, ?string $message = null): JsonResponse
    {
        return self::success(
            $resourceClass::collection($paginator->getCollection())->resolve(),
            $message,
            meta: ['pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ]],
        );
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     * @param  array<string, string>  $headers
     */
    public static function error(string $message, int $status, string $code, ?array $errors = null, array $headers = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message, 'code' => $code];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }
}
