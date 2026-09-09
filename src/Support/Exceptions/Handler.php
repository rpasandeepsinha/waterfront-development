<?php

declare(strict_types=1);

namespace Waterfront\Support\Exceptions;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Waterfront\Support\Enums\LoggingContextKeys;

class Handler extends ExceptionHandler
{
    /** @phpstan-ignore-next-line  */
    public function __construct(
        private readonly LoggerInterface $logger,
        Container $container,
    ) {
        parent::__construct($container);
    }

    public function register()
    {
        $this->reportable(function (Throwable $exception) {
            Integration::captureUnhandledException($exception);

            $this->logger->error(
                sprintf('Uncaught exception: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response
    {
        $request->headers->set('Accept', 'application/json');
        return parent::render($request, $e);
    }
}
