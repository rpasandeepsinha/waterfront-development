<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Support\Enums\LoggingContextKeys;

/**
 * @implements Rule<MethodCall>
 */
class LoggingContextKeysRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /**
         * If the method call is a valid logger method call we validate the
         * second argument. The first argument is the message as string
         * and the second argument is the context array per PSR3.
         */
        if ($this->isLoggerMethodCall($node, $scope)) {
            return $this->validateContextArray($this->argument($node, 1, 'context'));
        }

        /**
         * The LogContextBuilder collects the very same context array, so the
         * root keys it is handed must live up to the same standards as the
         * keys handed to the logger directly. The nested array given to
         * `withMeta()` is deliberately not checked; meta is a free
         * form bag of details that has no constants for its keys.
         */
        if ($this->isLogContextBuilderCall($node, $scope)) {
            return $this->validateLogContextBuilderKey($this->argument($node, 0, 'key'), $scope);
        }

        return [];
    }

    private function isLoggerMethodCall(MethodCall $node, Scope $scope): bool
    {
        $methodName = $this->methodName($node);

        // First we check if the given method is available on the LoggerInterface
        if (! in_array($methodName, get_class_methods(LoggerInterface::class), true)) {
            return false;
        }

        // If the method exists on the LoggerInterface we check if the call is on a LoggerInterface instance
        return $this->isCallOn(LoggerInterface::class, $node, $scope);
    }

    private function isLogContextBuilderCall(MethodCall $node, Scope $scope): bool
    {
        if ($this->methodName($node) !== 'with') {
            return false;
        }

        return $this->isCallOn(LogContextBuilder::class, $node, $scope);
    }

    private function isCallOn(string $className, MethodCall $node, Scope $scope): bool
    {
        return new ObjectType($className)->isSuperTypeOf($scope->getType($node->var))->yes();
    }

    /**
     * Validates the key argument of a `LogContextBuilder::with()` call.
     *
     * @return list<RuleError>
     */
    private function validateLogContextBuilderKey(?Expr $keyArg, Scope $scope): array
    {
        if (! $keyArg instanceof Expr) {
            return [];
        }

        if ($this->isLoggingContextKey($keyArg)) {
            return [];
        }

        /**
         * A key that is passed along from elsewhere, for example by a method
         * documented as taking a `LoggingContextKeys::*`, is accepted when
         * its type only allows values that are context key constants.
         */
        if (! $keyArg instanceof String_ && $this->isLoggingContextKeyType($keyArg, $scope)) {
            return [];
        }

        return [$this->createErrorFromKey($this->literalStringValue($keyArg))];
    }

    private function isLoggingContextKeyType(Expr $keyArg, Scope $scope): bool
    {
        $constantStrings = $scope->getType($keyArg)->getConstantStrings();

        if ($constantStrings === []) {
            return false;
        }

        $allowedValues = array_values(new ReflectionClass(LoggingContextKeys::class)->getConstants());
        return array_all($constantStrings, fn ($constantString) => in_array($constantString->getValue(), $allowedValues, true));
    }

    /**
     * Loop over all items in the given context array where we expect a valid
     * LoggingContextKey constant as key and any value as a context value.
     *
     * @return list<RuleError>
     */
    private function validateContextArray(?Expr $contextArg): array
    {
        /**
         * We can only inspect the keys of an array that is written out at the
         * call site. Anything else (a variable, a build() call, spread) is
         * checked where that array is constructed instead.
         */
        if (! $contextArg instanceof Array_) {
            return [];
        }

        $errors = [];

        foreach ($contextArg->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->key instanceof Expr && $this->isLoggingContextKey($item->key)) {
                continue;
            }

            $errors[] = $this->createErrorFromKey(
                $item->key instanceof Expr ? $this->literalStringValue($item->key) : null
            );
        }

        return $errors;
    }

    private function isLoggingContextKey(Expr $key): bool
    {
        if (! $key instanceof ClassConstFetch) {
            return false;
        }

        $className = $key->class instanceof Name ? $key->class->toString() : null;

        return $className === LoggingContextKeys::class;
    }

    /**
     * Resolves the argument at the given position, taking named arguments
     * into account so that `with(key: 'foo')` is checked as well.
     */
    private function argument(MethodCall $node, int $position, string $name): ?Expr
    {
        $positional = 0;

        foreach ($node->args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($arg->name instanceof Identifier) {
                if ($arg->name->toString() === $name) {
                    return $arg->value;
                }

                continue;
            }

            if ($positional === $position) {
                return $arg->value;
            }

            $positional++;
        }

        return null;
    }

    private function methodName(MethodCall $node): ?string
    {
        return $node->name instanceof Identifier ? $node->name->toString() : null;
    }

    private function literalStringValue(Expr $node): ?string
    {
        return $node instanceof String_ ? $node->value : null;
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
