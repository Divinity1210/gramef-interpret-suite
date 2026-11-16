<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Str;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
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
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        if (app()->bound('sentry') && $this->shouldReport($exception)){
            app('sentry')->captureException($exception);
        }
    
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthorizationException) {
            $routeName = optional($request->route())->getName();
            $isJobRoute = is_string($routeName) && (Str::startsWith($routeName, 'interpreter-jobs.') || Str::startsWith($routeName, 'translator-jobs.'));

            if ($isJobRoute) {
                $message = trim((string) $exception->getMessage());
                if ($message === '' || in_array($message, ['This action is unauthorized.', 'This action is unauthorised.'], true)) {
                    $job = $request->route('interpreterJob') ?? $request->route('translatorJob');
                    $message = $job && isset($job->reference)
                        ? 'This job is no longer available. It may have been taken or cancelled.'
                        : 'This action is no longer available.';
                }

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 403);
                }

                if (is_string($routeName) && Str::startsWith($routeName, 'translator-jobs.')) {
                    return redirect()->route('translator-jobs.index')->with('failure', $message);
                }

                return redirect()->route('interpreter-jobs.index')->with('failure', $message);
            }
        }

        return parent::render($request, $exception);
    }
}
