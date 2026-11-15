<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
        });
    }

    /**
     * Render server errors through a safe, branded response regardless of APP_DEBUG.
     * The framework exception message is intentionally never included in the response.
     */
    public function render($request, Throwable $exception): Response
    {
        $response = parent::render($request, $exception);

        if ($response->getStatusCode() < 500) {
            return $response;
        }

        $status = $response->getStatusCode();
        $message = $status === 503
            ? (string) config('planora.maintenance.message')
            : 'Planora could not complete that request. Please try again.';

        if ($request instanceof Request && ($request->expectsJson() || $request->is('api/*'))) {
            $safeResponse = response()->json(['message' => $message], $status);
        } else {
            $safeResponse = response()->view('errors.page', [
                'code' => $status,
                'title' => $status === 503 ? 'We will be back soon' : 'Something went wrong',
                'description' => $message,
                'retryAfter' => $status === 503 ? config('planora.maintenance.retry_after', 60) : null,
            ], $status);
        }

        foreach ($response->headers->all() as $name => $values) {
            if (strtolower($name) === 'content-type' || strtolower($name) === 'content-length') {
                continue;
            }

            foreach ($values as $value) {
                $safeResponse->headers->set($name, $value, false);
            }
        }

        if ($status === 503 && ! $safeResponse->headers->has('Retry-After')) {
            $safeResponse->headers->set('Retry-After', (string) config('planora.maintenance.retry_after', 60));
        }

        return $safeResponse;
    }
}
