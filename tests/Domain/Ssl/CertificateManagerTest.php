<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ssl\Services\CertificateManager;

/**
 * Tests for the certificate manager.
 */
#[CoversClass(CertificateManager::class)]
class CertificateManagerTest extends IntegrationTestCase
{
    private string $domain = 'sandwave.test';

    private CertificateManager $certificateManager;

    private string $sslDisk;

    public function setUp(): void
    {
        parent::setUp();

        $this->sslDisk = $this->getConfiguration()->getAsString('filesystems.cloud');
        $this->certificateManager = self::resolve(CertificateManager::class);
    }

    #[Test]
    public function saveRootCertificate(): void
    {
        Storage::fake($this->sslDisk);

        $certificate = require __DIR__ . '/data/root_certificate.php';

        $this->certificateManager->saveRootCertificate($this->domain, $certificate);

        self::assertSame($certificate, $this->certificateManager->getRootCertificate($this->domain));
    }

    #[Test]
    public function saveIntermediateCertificate(): void
    {
        Storage::fake($this->sslDisk);

        $certificate = require __DIR__ . '/data/intermediate_certificate.php';
        $this->certificateManager->saveIntermediateCertificate($this->domain, $certificate);

        self::assertSame($certificate, $this->certificateManager->getIntermediateCertificate($this->domain));
    }

    #[Test]
    public function saveMainCertificate(): void
    {
        Storage::fake($this->sslDisk);

        $certificate = require __DIR__ . '/data/main_certificate.php';
        $this->certificateManager->saveMainCertificate($this->domain, $certificate);

        self::assertSame($certificate, $this->certificateManager->getMainCertificate($this->domain));
    }
}
