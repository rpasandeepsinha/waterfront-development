<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ssl\CsrPersistanceStrategies\OpenSslExtensionStrategy;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\Storage\KeyCloud;
use Waterfront\Domain\Ssl\Storage\LocalDisk;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(CsrManager::class)]
class CsrManagerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'sandwave.testing';

    /**
     * @var mixed[]
     */
    private const array DEFAULT_CUSTOMER_DATA = [
        'name'       => 'Versio',
        'department' => 'Support',
        'address'    => [
            'city'         => 'Lelystad',
            'province'     => 'Flevoland',
            'country_code' => 'NL',
        ],
    ];

    private CsrManager $csrManager;

    private Filesystem $sslDisk;

    private string $localTestDisk = 'csr_manager_test';

    public function setUp(): void
    {
        parent::setUp();

        $this->sslDisk = Storage::fake($this->getConfiguration()->getAsString('filesystems.cloud'));
        $cryptoKey = $this->getConfiguration()->getAsString('app.ssl_crypto');

        Storage::fake($this->localTestDisk);

        $this->app->singleton(CsrManager::class, fn (): CsrManager => new CsrManager(
            self::resolve(OpenSslExtensionStrategy::class),
            new KeyCloud($cryptoKey, $this->sslDisk),
            new LocalDisk(
                $this->app->storagePath('framework/testing/disks/' . $this->localTestDisk)
            )
        ));

        $this->csrManager = self::resolve(CsrManager::class);
    }

    #[Test]
    public function createCsrMissingAddress(): void
    {
        $this->expectExceptionMessageIsOrContains('A validation error occurred while creating CSR subject data');

        $faultyCustomer = [
            'name'       => 'Sandwave',
            'department' => 'Team Mind',
        ];

        foreach ($this->getCsrManagers() as $csrManager) {
            $csrManager->create(
                $faultyCustomer,
                self::DOMAIN
            );
        }
    }

    #[Test]
    public function createCsrMissingRequiredFields(): void
    {
        $this->expectExceptionMessageIsOrContains('A validation error occurred while creating CSR subject data');

        $faultyCustomer = [
            'name'       => '',
            'department' => 'Team Mind',
            'address'    => [
                'city'         => 'Utrecht',
                'country_code' => 'NL',
            ],
        ];

        foreach ($this->getCsrManagers() as $csrManager) {
            $csrManager->create(
                $faultyCustomer,
                self::DOMAIN
            );
        }
    }

    /**
     * @param mixed[] $customerData
     * @param mixed[] $expectedResult
     */
    #[DataProvider('csrDataProvider')]
    #[Test]
    public function createCsr(
        array $customerData,
        ?int $encryptionStrength,
        array $expectedResult
    ): void {
        foreach ($this->getCsrManagers() as $csrManager) {
            $this->createCsrWithManager($csrManager, $customerData, $encryptionStrength, $expectedResult);
        }
    }

    #[Test]
    public function createPrivateKey(): void
    {
        $this->csrManager->create(
            self::DEFAULT_CUSTOMER_DATA,
            self::DOMAIN
        );

        $privateKey = $this->csrManager->getPrivateKey(self::DOMAIN);
        self::assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $privateKey);
        self::assertStringEndsWith('-----END PRIVATE KEY-----' . PHP_EOL, $privateKey);
    }

    #[Depends('createPrivateKey')]
    #[Test]
    public function privateKeyIsEncrypted(): void
    {
        $this->csrManager->create(
            self::DEFAULT_CUSTOMER_DATA,
            self::DOMAIN
        );

        // the original private key file
        $filePath = self::DOMAIN . '/' . self::DOMAIN . '.key';
        self::assertFalse($this->sslDisk->exists($filePath));

        // the encrypted private key file
        $encryptedFilePath = $filePath . '.crypt';
        self::assertTrue($this->sslDisk->exists($encryptedFilePath));

        // decrypt the encrypted .crypt file...
        $ciphertext = $this->sslDisk->get($encryptedFilePath) ?? '';

        $cryptoKey = self::resolve(ConfigurationInterface::class)->getAsString('app.ssl_crypto');
        $cryptoKey = Key::loadFromAsciiSafeString($cryptoKey);
        $privateKey = Crypto::decrypt($ciphertext, $cryptoKey);
        // ...and check its content
        self::assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $privateKey);
    }

    #[Depends('createPrivateKey')]
    #[Test]
    public function createWildcardCertificate(): void
    {
        $wildcardDomain = '*.sandwaveio.testing';
        $this->csrManager->create(
            self::DEFAULT_CUSTOMER_DATA,
            $wildcardDomain
        );

        // make sure the CSR+key are created:
        self::assertNotEmptyString($this->csrManager->getRawCsr($wildcardDomain));
        self::assertNotEmptyString($this->csrManager->getPrivateKey($wildcardDomain));

        // delete the files
        $this->csrManager->delete($wildcardDomain);
    }

    #[Depends('createPrivateKey')]
    #[Test]
    public function deleteSucceeds(): void
    {
        $this->csrManager->create(
            self::DEFAULT_CUSTOMER_DATA,
            self::DOMAIN
        );

        // make sure the CSR+key are created:
        self::assertNotEmptyString($this->csrManager->getRawCsr(self::DOMAIN));
        self::assertNotEmptyString($this->csrManager->getPrivateKey(self::DOMAIN));

        // delete the files
        $this->csrManager->delete(self::DOMAIN);

        // check that they are gone
        $this->expectException(FileNotFoundException::class);
        $this->csrManager->getRawCsr(self::DOMAIN);
    }

    #[Test]
    public function hasPrivateKey(): void
    {
        $this->csrManager->delete(self::DOMAIN);
        self::assertFalse($this->csrManager->hasPrivateKey(self::DOMAIN));

        $this->csrManager->create(
            self::DEFAULT_CUSTOMER_DATA,
            self::DOMAIN
        );

        self::assertTrue($this->csrManager->hasPrivateKey(self::DOMAIN));
    }

    #[Depends('createPrivateKey')]
    #[Test]
    public function updatePrivateKey(): void
    {
        $pkeyUpdate = require __DIR__ . '/data/pkey_update.php';

        $this->csrManager->create(
            self::DEFAULT_CUSTOMER_DATA,
            self::DOMAIN
        );

        $updated = $this->csrManager->updatePrivateKey(self::DOMAIN, $pkeyUpdate);

        self::assertTrue($updated);

        $privateKey = $this->csrManager->getPrivateKey(self::DOMAIN);

        self::assertSame($pkeyUpdate, $privateKey);
    }

    /**
     * @return mixed[]
     */
    public static function csrDataProvider(): array
    {
        return [
            // Normal data without specifying encryption strength
            [
                'customerData'       => self::DEFAULT_CUSTOMER_DATA,
                'encryptionStrength' => null,
                'expectedResult'     => [
                    'countryName'            => 'NL',
                    'stateOrProvinceName'    => 'Flevoland',
                    'localityName'           => 'Lelystad',
                    'organizationName'       => 'Versio',
                    'organizationalUnitName' => 'Support',
                    'commonName'             => self::DOMAIN,
                    'publicKeyAlgorithm'     => 'rsaEncryption',
                    'encryptionStrength'     => 2048,
                ],
            ],
            // Normal data plus encryption strength
            [
                'customerData'       => self::DEFAULT_CUSTOMER_DATA,
                'encryptionStrength' => 4096,
                'expectedResult'     => [
                    'countryName'            => 'NL',
                    'stateOrProvinceName'    => 'Flevoland',
                    'localityName'           => 'Lelystad',
                    'organizationName'       => 'Versio',
                    'organizationalUnitName' => 'Support',
                    'commonName'             => self::DOMAIN,
                    'publicKeyAlgorithm'     => 'rsaEncryption',
                    'encryptionStrength'     => 4096,
                ],
            ],
            // Malicious data for testing sanitization
            [
                'customerData'       => [
                    'name'       => 'Versio',
                    'department' => 'Support',
                    'address'    => [
                        'city'         => 'Lelystad',
                        'province'     => 'Flevoland/CN=maliciousdomain.com',
                        'country_code' => 'NL',
                    ],
                ],
                'encryptionStrength' => null,
                'expectedResult'     => [
                    'countryName'            => 'NL',
                    'stateOrProvinceName'    => 'Flevoland',
                    'localityName'           => 'Lelystad',
                    'organizationName'       => 'Versio',
                    'organizationalUnitName' => 'Support',
                    'commonName'             => self::DOMAIN,
                    'publicKeyAlgorithm'     => 'rsaEncryption',
                    'encryptionStrength'     => 2048,
                ],
            ],
            // Missing department
            [
                'customerData'       => [
                    'name'    => 'Sandwave',
                    'address' => [
                        'city'         => 'Lelystad',
                        'province'     => 'Flevoland',
                        'country_code' => 'NL',
                    ],
                ],
                'encryptionStrength' => null,
                'expectedResult'     => [
                    'countryName'            => 'NL',
                    'stateOrProvinceName'    => 'Flevoland',
                    'localityName'           => 'Lelystad',
                    'organizationName'       => 'Versio',
                    'organizationalUnitName' => 'Support',
                    'commonName'             => self::DOMAIN,
                    'publicKeyAlgorithm'     => 'rsaEncryption',
                    'encryptionStrength'     => 2048,
                ],
            ],
        ];
    }

    /**
     * @return CsrManager[]
     */
    private function getCsrManagers(): array
    {
        return [
            self::resolve(CsrManager::class),
        ];
    }

    /**
     * Tests that the Csr Manager generates a correct CSR file. This is an internal method where we also provide
     * the CSR Manager in order to be able to test multiple adapters.
     *
     * @param mixed[] $customerData
     * @param mixed[] $expectedResult
     */
    private function createCsrWithManager(
        CsrManager $csrManager,
        array $customerData,
        ?int $encryptionStrength,
        array $expectedResult
    ): void {
        if ($encryptionStrength !== null) {
            $csrManager->create(
                $customerData,
                self::DOMAIN,
                $encryptionStrength
            );
        } else {
            $csrManager->create(
                $customerData,
                self::DOMAIN
            );
        }

        $rawCsr = $csrManager->getRawCsr(self::DOMAIN);
        self::assertStringStartsWith('-----BEGIN CERTIFICATE REQUEST-----', $rawCsr);
        self::assertStringEndsWith('-----END CERTIFICATE REQUEST-----' . PHP_EOL, $rawCsr);

        $csrData = $csrManager->getCsrData(self::DOMAIN);
        $csrDataArray = $csrData->toArray();

        if (array_key_exists('signatureAlgorithm', $csrDataArray) && $csrDataArray['signatureAlgorithm'] !== null) {
            self::assertSame('sha256WithRSAEncryption', $csrDataArray['signatureAlgorithm']);
            unset($csrDataArray['signatureAlgorithm']);
        }

        self::assertSame($expectedResult, $csrDataArray);
    }

    private function assertNotEmptyString(mixed $string): void
    {
        self::assertIsString($string);
        self::assertNotEmpty($string);
    }
}
