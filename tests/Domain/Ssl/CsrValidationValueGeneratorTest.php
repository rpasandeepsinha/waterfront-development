<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Ssl\Services\CsrValidationValueGenerator;

#[CoversClass(CsrValidationValueGenerator::class)]
class CsrValidationValueGeneratorTest extends TestCase
{
    private const string TEST_CSR = <<<CSR
    -----BEGIN CERTIFICATE REQUEST-----
    FOOBAR
    -----END CERTIFICATE REQUEST-----
    -----BEGIN NEW CERTIFICATE REQUEST-----
    BARFOO
    -----END NEW CERTIFICATE REQUEST-----
    CSR;

    #[Test]
    public function cnameValidationHost(): void
    {
        $valueGenerator = new CsrValidationValueGenerator();
        self::assertSame('_047e00667d90240deb9761316ea0c3ba', $valueGenerator->getCnameValidationHost(self::TEST_CSR));
    }

    #[Test]
    public function cnameValidationValue(): void
    {
        $valueGenerator = new CsrValidationValueGenerator();
        self::assertSame(
            '62dd49780cbd7394665707e4803b393d.559c46f98fa266374f4ebd9b26510876.sectigo.com.',
            $valueGenerator->getCnameValidationValue(self::TEST_CSR),
        );
    }

    #[Test]
    public function fileValidationFileName(): void
    {
        $valueGenerator = new CsrValidationValueGenerator();
        self::assertSame(
            '047E00667D90240DEB9761316EA0C3BA.txt',
            $valueGenerator->getFileValidationFileName(self::TEST_CSR),
        );
    }

    #[Test]
    public function fileValidationValue(): void
    {
        $valueGenerator = new CsrValidationValueGenerator();
        self::assertSame(
            '62dd49780cbd7394665707e4803b393d559c46f98fa266374f4ebd9b26510876 sectigo.com',
            $valueGenerator->getFileValidationValue(self::TEST_CSR),
        );
    }
}
