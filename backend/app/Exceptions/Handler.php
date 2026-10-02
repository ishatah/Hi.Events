<?php

namespace HiEvents\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sentry\Laravel\Facade as Sentry;
use Sentry\State\Scope;
use Symfony\Component\Routing\Exception\ResourceNotFoundException as SymfonyResourceNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        ResourceNotFoundException::class,
        ResourceConflictException::class,
        SymfonyResourceNotFoundException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function report(Throwable $e)
    {
        if ($this->shouldReport($e) && app()->bound('sentry')) {
            try {
                $user = auth()->user();
                if ($user) {
                    $impersonatorId = $this->impersonatorId();

                    Sentry::configureScope(function (Scope $scope) use ($user, $impersonatorId): void {
                        $scope->setUser([
                            'id' => $user->id,
                        ]);

                        if ($impersonatorId !== null) {
                            $scope->setTag('is_impersonating', 'true');
                            $scope->setTag('impersonator_id', (string) $impersonatorId);
                        }
                    });
                }
            } catch (Throwable) {
            }
            Sentry::captureException($e);
        }

        parent::report($e);
    }

    private function impersonatorId(): int|string|null
    {
        try {
            $payload = auth()->payload();
        } catch (Throwable) {
            return null;
        }

        return $payload->get('is_impersonating', false)
            ? $payload->get('impersonator_id')
            : null;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return JsonResponse|Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ResourceNotFoundException || $exception instanceof SymfonyResourceNotFoundException) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Resource not found',
            ], 404);
        }

        return parent::render($request, $exception);
    }
}
