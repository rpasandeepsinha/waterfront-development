<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use Illuminate\Support\Facades\Log;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Psr\Log\LoggerInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

/**
 * @implements Rule<StaticCall>
 */
class LoggingContextKeysRuleFacade implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /**
         * If the method call is not a valid logger method call we
         * skip any further processing. We do this first because
         * this is faster than the rest of Node the checking.
         */
        if (! $this->isLoggerMethodCall($node)) {
            return [];
        }

        /**
         * Retrieve the second argument from LoggerInterface call
         * The first argument is the message as string and the
         * second argument is the context as array per PSR3.
         */
        $contextArg = $node->args[1]->value ?? null;

        /**
         * We satisfy PHPStan here by asserting that the given
         * argument is an array. This should always be true
         * but stan doesn't know any better from the Node.
         */
        if (! $contextArg instanceof Array_) {
            return [];
        }

        $errors = [];

        /**
         * Loop over all items in the context array argument where
         * we expect a valid LoggingContextKey constant as key
         * and any value as a valid logging context value.
         */
        foreach ($contextArg->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($this->arrayKeyIsLoggingContextKey($item->key)) {
                continue;
            }

            $keyValue = $item->key instanceof String_ ? $item->key->value : null;
            $errors[] = $this->createErrorFromKey($keyValue);
        }

        return $errors;
    }

    private function isLoggerMethodCall(StaticCall $node): bool
    {
        $methodName = $node->name instanceof Identifier ? $node->name->toString() : null;

        // First we check if the given method is available on the LoggerInterface
        if (! in_array($methodName, get_class_methods(LoggerInterface::class), true)) {
            return false;
        }

        return $node->class instanceof Name && $node->class->toString() === Log::class;
    }

    private function arrayKeyIsLoggingContextKey(?Node $key): bool
    {
        if (! $key instanceof ClassConstFetch) {
            return false;
        }

        $className = $key->class instanceof Name ? $key->class->toString() : null;

        return $className === LoggingContextKeys::class;
    }

    private function createErrorFromKey(?string $keyValue): RuleError
    {
        return RuleErrorBuilder::message(
            sprintf(
                'The logging context key "%s" is not a %s constant. See https://yh-jira.atlassian.net/wiki/spaces/DEV/pages/1562640571/Logging',
                $keyValue ?? 'null',
                LoggingContextKeys::class,
            )
        )->identifier('sandwave.custom')->build();
    }
}
