<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;

/**
 * Disallow AcronisClient as DI, recommend AcronisClientFactory.
 *
 * @implements Rule<Node>
 */
class NoAcronisClientInjectionRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $acronisType = AcronisClient::class;

        if ($node instanceof ClassMethod && $node->name->toString() === '__construct') {
            foreach ($node->params as $param) {
                if ($param->type instanceof Name) {
                    if ($param->type->toString() === $acronisType) {
                        $errors[] = RuleErrorBuilder::message(
                            'Do not inject the AcronisClient. Use AcronisClientFactory instead.'
                        )->build();
                    }
                }
            }
        }

        return $errors;
    }
}
