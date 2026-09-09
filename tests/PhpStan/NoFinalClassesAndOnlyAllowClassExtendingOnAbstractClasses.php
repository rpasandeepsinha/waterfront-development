<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use function array_unique;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use function in_array;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;

use function sprintf;

/** @implements Rule<Class_> */
class NoFinalClassesAndOnlyAllowClassExtendingOnAbstractClasses implements Rule
{
    /**
     * @var array<int, class-string>
     */
    private static array $defaultClassesAllowedToBeExtended = [
        'ArrayIterator',
        'BadMethodCallException',
        'Exception',
        'InvalidArgumentException',
        'RuntimeException',
        'UnexpectedValueException',
        ModelNotFoundException::class,
    ];

    /**
     * @var array<int, class-string>
     */
    private readonly array $classesAllowedToBeExtended;

    /**
     * @param array<int, class-string> $classesAllowedToBeExtended
     */
    public function __construct(array $classesAllowedToBeExtended = [])
    {
        $this->classesAllowedToBeExtended = array_unique([
            ...self::$defaultClassesAllowedToBeExtended,
            ...$classesAllowedToBeExtended,
        ]);
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if ($node->isAnonymous()) {
            return [];
        }

        if ($node->isFinal()) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'Making class "%s" final is not allowed. This prevents mocking in testing. '
                    . 'We instead check if classes that are being extended are always abstract classes.',
                    $node->namespacedName?->toString() ?? 'unknown',
                )
            )->identifier('sandwave.custom')->build();
        }

        if (! $node->extends instanceof Name) {
            return $errors;
        }

        /** @phpstan-ignore-next-line */
        $reflectionClass = new ReflectionClass($node->extends->toString());
        if ($reflectionClass->isAbstract()) {
            return $errors;
        }

        $extendedClassName = $node->extends->toString();

        if (in_array($extendedClassName, $this->classesAllowedToBeExtended, true)) {
            return $errors;
        }

        if ($node->namespacedName === null) {
            return $errors;
        }

        $errors[] = RuleErrorBuilder::message(
            sprintf(
                'Class "%s" is not allowed to extend "%s". Try to extend abstract classes only.',
                $node->namespacedName->toString(),
                $extendedClassName
            )
        )->identifier('sandwave.custom')->build();

        return $errors;
    }
}
