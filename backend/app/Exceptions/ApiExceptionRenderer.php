<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Turns any exception thrown under /api/* into the standard error envelope
 * (see App\Support\ApiResponse). Registered in bootstrap/app.php.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => ApiResponse::error(
                __('api.validation_failed'), 422, 'validation_error', $exception->errors(),
            ),
            $exception instanceof AuthenticationException => ApiResponse::error(__('api.unauthenticated'), 401, 'unauthenticated'),
            $exception instanceof AuthorizationException => ApiResponse::error(
                $exception->getMessage() ?: __('api.forbidden'), 403, 'forbidden',
            ),
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => ApiResponse::error(__('api.not_found'), 404, 'not_found'),
            $exception instanceof TooManyRequestsHttpException => ApiResponse::error(
                __('api.too_many_requests'), 429, 'too_many_requests', headers: $exception->getHeaders(),
            ),
            $exception instanceof HttpExceptionInterface => $this->renderHttpException($exception),
            default => ApiResponse::error(
                config('app.debug') ? $exception->getMessage() : __('api.server_error'), 500, 'server_error',
            ),
        };
    }

    private function renderHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        $code = match ($status) {
            403 => 'forbidden',
            405 => 'method_not_allowed',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };

        $message = match ($status) {
            403 => $exception->getMessage() ?: __('api.forbidden'),
            405 => __('api.method_not_allowed'),
            default => $status >= 500 ? __('api.server_error') : ($exception->getMessage() ?: __('api.request_failed')),
        };

        return ApiResponse::error($message, $status, $code, headers: $exception->getHeaders());
    }
}
