<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ssl\Services\SslService;

#[CoversClass(SslService::class)]
class SslServiceValidationTest extends IntegrationTestCase
{
    private SslService $sslService;

    /**
     * @var array<string>
     */
    private array $expectedCsrData = [
        'countryName' => 'NL',
        'commonName' => 'test.com',
        'localityName' => 'vlissingen',
        'organizationName' => 'sandwave',
        'stateOrProvinceName' => 'zeeland',
        'organizationalUnitName' => 'development',
    ];

    /**
     * @var array<string>
     */
    private array $expectedCsrWildCardData = [
        'countryName' => 'NL',
        'commonName' => '*.test.com',
        'localityName' => 'Vlissingen',
        'organizationName' => 'sandwave',
        'stateOrProvinceName' => 'Zeeland',
        'organizationalUnitName' => 'development',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->sslService = self::resolve(SslService::class);
    }

    #[Test]
    public function csrValidate(): void
    {
        //csr based on test.com
        $csr = include __DIR__ . '/../data/csr.php';

        $result = $this->sslService->validate($csr, 'test.com', null);

        self::assertSame($this->expectedCsrData, $result);
    }

    #[Test]
    public function csrWildcardValidate(): void
    {
        //csr based on test.com
        $csr = include __DIR__ . '/../data/csr_wildcard.php';

        $result = $this->sslService->validate($csr, 'test.com', null);

        self::assertSame($this->expectedCsrWildCardData, $result);
    }

    #[Test]
    public function csrValidateWrongDomain(): void
    {
        //csr based on test.com
        $csr = include __DIR__ . '/../data/csr.php';

        $result = $this->sslService->validate($csr, 'test.nl', null);
        self::assertFalse($result);
    }

    #[Test]
    public function csrValidateWrongCsr(): void
    {
        //csr based on test.com
        $csr = include __DIR__ . '/../data/csr_missing_value.php';

        $result = $this->sslService->validate($csr, 'test.com', null);

        self::assertFalse($result);
    }
}
