<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;
use Waterfront\Support\Enums\LoggingContextKeys;

/**
 * Formats an exception provided in the context to short readable context values under the 'exception' key.
 *
 * Makes it possible to log a message like:
 *
 * <pre>
 * <code>
 *   <?php
 *   // @var \Psr\Log\LoggerInterface $logger
 *   $logger->info('Some process failed because: {exception.message}', ['exception' => $caughtException]);
 *   ?>
 * <code>
 * </pre>
 */
class ThrowableExceptionContext implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $exception = $this->extractExceptionFromRecordContext($context);

        if (! $exception instanceof Throwable) {
            return $record;
        }

        $context['exception.type'] = $exception::class . "({$exception->getCode()})";
        $context['exception.message'] = $exception->getMessage();
        $context['exception.thrown_at'] = "{$exception->getFile()} line {$exception->getLine()}";
        $context['exception.trace'] = $exception->getTraceAsString();
        $context['exception.previous'] = (string) $exception->getPrevious();

        return $record->with(
            context: $context
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractExceptionFromRecordContext(array &$context): ?Throwable
    {
        $exception = $context[LoggingContextKeys::EXCEPTION] ?? null;
        if ($exception instanceof Throwable) {
            unset($context[LoggingContextKeys::EXCEPTION]);
            return $exception;
        }

        return null;
    }
}
