<?php

use App\Http\Middleware\EnsureBackofficeAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\ApiResponder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'backoffice' => EnsureBackofficeAccess::class,
            'active' => EnsureUserIsActive::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof HttpResponseException => $e->getResponse(),
                $e instanceof ValidationException => ApiResponder::error(collect($e->errors())->flatten()->first() ?? 'The given data was invalid.', 422, $e->errors()),
                $e instanceof AuthenticationException => ApiResponder::error('Unauthenticated', 401),
                $e instanceof UnauthorizedException, $e instanceof AccessDeniedHttpException => ApiResponder::error('You do not have permission to perform this action', 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponder::error('Resource not found', 404),
                $e instanceof MethodNotAllowedHttpException => ApiResponder::error('Method not allowed', 405),
                $e instanceof ThrottleRequestsException => ApiResponder::error('Too many requests, please slow down', 429),
                $e instanceof HttpExceptionInterface => ApiResponder::error($e->getMessage() ?: 'Request failed', $e->getStatusCode()),
                default => config('app.debug') ? null : ApiResponder::error('Something went wrong on our end', 500),
            };
        });
    })->create();
