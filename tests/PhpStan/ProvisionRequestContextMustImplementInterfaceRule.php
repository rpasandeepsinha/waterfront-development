<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;
use ReflectionException;

use function str_contains;

use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;

/**
 * Provision request classes that declare a `$context` property of type `UuidInterface`
 * must implement `ProvisionContextRequestInterface` (directly or through a parent class).
 *
 * @implements Rule<Class_>
 */
class ProvisionRequestContextMustImplementInterfaceRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->isProvisionRequest($scope)) {
            return [];
        }

        if (! $this->hasContextProperty($node)) {
            return [];
        }

        if ($this->implementsContextInterface($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Provision request class has a $context property but does not implement '
                . ProvisionContextRequestInterface::class . '. ',
            )
                ->identifier('sandwave.custom')
                ->build(),
        ];
    }

    private function isProvisionRequest(Scope $scope): bool
    {
        $filePath = $scope->getFile();

        return str_contains($filePath, 'src/Domain/Provision/')
            && str_contains($filePath, '/Requests/');
    }

    private function hasContextProperty(Class_ $node): bool
    {
        foreach ($node->getProperties() as $propertyNode) {
            foreach ($propertyNode->props as $prop) {
                if ($prop->name->toString() === 'context') {
                    return true;
                }
            }
        }

        $constructor = $node->getMethod('__construct');

        if ($constructor !== null) {
            foreach ($constructor->params as $param) {
                if (
                    $param->var instanceof Variable
                    && $param->var->name === 'context'
                    && $param->flags !== 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function implementsContextInterface(Class_ $node): bool
    {
        $className = $node->namespacedName?->toString();

        if ($className === null) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);

            return $reflection->implementsInterface(ProvisionContextRequestInterface::class);
        } catch (ReflectionException) {
            return false;
        }
    }
}
