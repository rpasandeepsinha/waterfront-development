<?php

declare(strict_types=1);

namespace Tests\Infra\PasswordGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\PasswordGenerator\AbstractGenerator;
use Waterfront\Infra\PasswordGenerator\DTO\Rule;
use Waterfront\Infra\PasswordGenerator\Enums\CharacterSet;
use Waterfront\Infra\PasswordGenerator\Exceptions\PasswordGeneratorException;

#[CoversClass(AbstractGenerator::class)]
class GeneratorTest extends TestCase
{
    #[Test]
    public function overrideMinLengthThroughInheritance(): void
    {
        $generator = new class () extends AbstractGenerator {
            public const int MIN_LENGTH = 100;

            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
            }
        };

        self::assertSame(100, strlen($generator->generatePassword()));
    }

    #[Test]
    public function setLengthFailedToShort(): void
    {
        $length = 4;
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
            }
        };

        $this->expectException(PasswordGeneratorException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'The given PasswordLength (%d) is too short, the minimum is %d',
                $length,
                AbstractGenerator::MIN_LENGTH
            )
        );

        $generator->generatePassword($length);
    }

    #[Test]
    public function generatePasswordFailedDueMissingRules(): void
    {
        $this->expectException(PasswordGeneratorException::class);
        $this->expectExceptionMessageIs('There are no rules attached, so we can not generate a password');

        $generator = new class () extends AbstractGenerator {
        };

        $generator->generatePassword();
    }

    #[Test]
    public function addRuleFailedDueDuplicateCharsetRule(): void
    {
        $this->expectException(PasswordGeneratorException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'There is already a rule for the charset : %s applied',
                CharacterSet::LOWERCASE->name,
            )
        );

        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
            }
        };

        $generator->generatePassword();
    }

    #[Test]
    public function generatePasswordFailedDueExceedingMaxLength(): void
    {
        $this->expectException(PasswordGeneratorException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'The maximum length (%d) will be exceeded by te total of minimum occurrences (%d) of the rules',
                AbstractGenerator::MIN_LENGTH,
                12
            )
        );

        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
                $this->addRule(new Rule(CharacterSet::UPPERCASE));
                $this->addRule(new Rule(CharacterSet::DIGIT, 10));
            }
        };

        $generator->generatePassword();
    }

    #[Test]
    public function generatePasswordRightLength(): void
    {
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
            }
        };

        $password = $generator->generatePassword();
        self::assertSame(AbstractGenerator::MIN_LENGTH, strlen($password));
    }

    #[Test]
    public function generatePassword(): void
    {
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
                $this->addRule(new Rule(CharacterSet::UPPERCASE));
                $this->addRule(new Rule(CharacterSet::DIGIT));
                $this->addRule(new Rule(CharacterSet::PUNCT));
            }
        };

        $password = $generator->generatePassword();

        self::assertMatchesRegularExpression('/[[:digit:]]/', $password);
        self::assertMatchesRegularExpression('/[[:lower:]]/', $password);
        self::assertMatchesRegularExpression('/[[:upper:]]/', $password);
        self::assertMatchesRegularExpression('/[[:punct:]]/', $password);
    }

    #[Test]
    public function generatePasswordWithMaxOccurrence(): void
    {
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
                $this->addRule(new Rule(CharacterSet::UPPERCASE));
                $this->addRule(new Rule(characterSet: CharacterSet::DIGIT, maxOccurrence: 2));
            }
        };

        $password = $generator->generatePassword(32);

        self::assertMatchesRegularExpression('/[[:digit:]]/', $password);
        self::assertMatchesRegularExpression('/[[:lower:]]/', $password);
        self::assertMatchesRegularExpression('/[[:upper:]]/', $password);

        self::assertLessThanOrEqual(2, preg_match_all('/[[:digit:]]/', $password));
    }

    #[Test]
    public function generatePasswordWithMinOccurrence(): void
    {
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
                $this->addRule(new Rule(CharacterSet::UPPERCASE));
                $this->addRule(new Rule(characterSet: CharacterSet::DIGIT, minOccurrence: 5));
            }
        };

        $password = $generator->generatePassword(32);

        self::assertMatchesRegularExpression('/[[:digit:]]/', $password);
        self::assertMatchesRegularExpression('/[[:lower:]]/', $password);
        self::assertMatchesRegularExpression('/[[:upper:]]/', $password);

        self::assertGreaterThanOrEqual(5, preg_match_all('/[[:digit:]]/', $password));
    }

    #[Test]
    public function generatePasswordWithExactOneOccurrence(): void
    {
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE));
                $this->addRule(new Rule(CharacterSet::UPPERCASE));
                $this->addRule(new Rule(characterSet: CharacterSet::DIGIT, minOccurrence: 1, maxOccurrence: 1));
            }
        };

        $password = $generator->generatePassword(32);

        self::assertMatchesRegularExpression('/[[:digit:]]/', $password);
        self::assertMatchesRegularExpression('/[[:lower:]]/', $password);
        self::assertMatchesRegularExpression('/[[:upper:]]/', $password);

        self::assertSame(1, preg_match_all('/[[:digit:]]/', $password));
    }

    #[Test]
    public function generatePasswordWithExclusions(): void
    {
        $generator = new class () extends AbstractGenerator {
            public function __construct()
            {
                $this->addRule(new Rule(CharacterSet::LOWERCASE, exclusions: 'k'));
                $this->addRule(new Rule(CharacterSet::UPPERCASE));
                $this->addRule(new Rule(characterSet: CharacterSet::DIGIT));
            }
        };

        $password = $generator->generatePassword(32);

        self::assertSame(0, preg_match_all('/k/', $password));
    }
}
