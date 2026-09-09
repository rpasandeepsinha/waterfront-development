<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function str_contains;
use function str_ends_with;

use Tests\IntegrationTestCase;

/**
 * Tests in the Provision domain (tests/Domain/Provision) may only extend
 * IntegrationTestCase when they live inside an "Integration" directory.
 * All other Provision tests must extend the base TestCase instead.
 *
 * @implements Rule<Class_>
 */
class ProvisionTestMustMatchDirectoryConventionRule implements Rule
{
    private const string INTEGRATION_TEST_CASE = IntegrationTestCase::class;

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
        if (! $this->isProvisionTest($scope, $node)) {
            return [];
        }

        if (! $this->extendsIntegrationTestCase($node)) {
            return [];
        }

        if ($this->isInsideIntegrationDirectory($scope)) {
            return [];
        }

        if ($this->isRepositoryTest($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Provision test extends IntegrationTestCase but is not in an Integration directory. '
                . 'Move this test to an Integration directory or extend TestCase instead.',
            )
                ->identifier('sandwave.custom')
                ->build(),
        ];
    }

    private function isProvisionTest(Scope $scope, Class_ $node): bool
    {
        $filePath = $scope->getFile();

        return str_contains($filePath, 'tests/Domain/Provision/')
            && $node->extends !== null
            && str_ends_with((string) $node->name, 'Test');
    }

    private function extendsIntegrationTestCase(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        return (string) $node->extends === self::INTEGRATION_TEST_CASE;
    }

    private function isInsideIntegrationDirectory(Scope $scope): bool
    {
        $filePath = $scope->getFile();

        return str_contains($filePath, '/Integration/') || str_contains($filePath, '/Intergration/');
    }

    private function isRepositoryTest(Class_ $node): bool
    {
        return str_contains((string) $node->name, 'Repository');
    }
}
