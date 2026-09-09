<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use function array_key_exists;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\CoversNothing;

use function preg_match;
use function preg_split;
use function sha1;
use function sprintf;

/**
 * This rules is taken from https://github.com/brainbits/phpstan-rules and
 * adjusted to be more useful to us (normally they only work for unit tests).
 *
 * @implements Rule<Class_>
 */
class CoversClassPresentRule implements Rule
{
    /** @var array<string, bool> */
    private $alreadyParsedDocComments = [];

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
        $messages = [];

        $isTest = (bool) $node->extends
            && $this->isTest((string) $node->name);

        $hasCovers = $this->processNodeAnnotation($node, $scope) || $this->processNodeAttribute($node, $scope);

        if ($isTest && ! $hasCovers) {
            $messages[] = RuleErrorBuilder::message('No @covers or #[CoversClass] found in test.')
                ->identifier('sandwave.custom')
                ->build();
        }

        return $messages;
    }

    public function processNodeAnnotation(Class_ $node, Scope $scope): bool
    {
        $lines = $this->getAnnotationLines($node, $scope);

        foreach ($lines as $lineContent) {
            $lineHasCovers = (bool) preg_match('/^(?:\s*\*\s*@(?:covers|coversDefaultClass)\h+)\\\\?(?<className>\w[^:\s]*)(?:::\S+)?\s*$/u', $lineContent, $matches);
            if ($lineHasCovers) {
                return true;
            }

            $lineHasCovers = (bool) preg_match('/^(?:\s*\/\*\*\s*@(?:covers|coversDefaultClass)\h+)\\\\?(?<className>\w[^:\s]*)(?:::\S+)?\s*\*\/\s*$/u', $lineContent, $matches);
            if ($lineHasCovers) {
                return true;
            }
        }

        return false;
    }

    public function processNodeAttribute(Class_ $node, Scope $scope): bool
    {
        if ($node->attrGroups === []) {
            return false;
        }

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ((string) $attr->name === CoversClass::class) {
                    return true;
                }

                if ((string) $attr->name === CoversFunction::class) {
                    return true;
                }

                if ((string) $attr->name === CoversNothing::class) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<string> */
    private function getAnnotationLines(Node $node, Scope $scope): array
    {
        $docComment = $node->getDocComment();
        if (! $docComment instanceof Doc) {
            return [];
        }

        $hash = sha1(
            sprintf(
                '%s:%s:%s:%s',
                $scope->getFile(),
                $docComment->getStartLine(),
                $docComment->getStartFilePos(),
                $docComment->getText(),
            ),
        );

        if (array_key_exists($hash, $this->alreadyParsedDocComments)) {
            return [];
        }

        $this->alreadyParsedDocComments[$hash] = true;

        $lines = [];
        foreach ((array) preg_split('/\R/u', $docComment->getText()) as $line) {
            $lines[] = (string) $line;
        }

        return $lines;
    }

    private function isTest(string $className): bool
    {
        return \str_ends_with($className, 'Test');
    }
}
