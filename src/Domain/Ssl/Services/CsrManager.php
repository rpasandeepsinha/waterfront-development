<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Waterfront\Domain\Ssl\CsrPersistanceStrategies\OpenSslExtensionStrategy;
use Waterfront\Domain\Ssl\Models\CsrData;
use Waterfront\Domain\Ssl\Models\CsrSubjectData;
use Waterfront\Domain\Ssl\Storage\KeyCloud;
use Waterfront\Domain\Ssl\Storage\LocalDisk;

class CsrManager
{
    public function __construct(
        private readonly OpenSslExtensionStrategy $openSsl,
        private readonly KeyCloud $cloudDisk,
        private readonly LocalDisk $localDisk
    ) {
    }

    /**
     * @param mixed[] $customerData
     */
    public function create(array $customerData, string $domain, int $encryptionStrength = 2048): void
    {
        $subjectData = CsrSubjectData::createFromCustomerData($customerData, $domain);

        $this->localDisk->prepareDirectory($domain);

        $this->openSsl->createAndStorePrivateKeyAndCsr(
            $subjectData,
            $encryptionStrength,
            $this->localDisk->getPrivateKeyPath($domain),
            $this->localDisk->getCsrPath($domain)
        );

        $this->storeCsr($domain);
        $this->storePrivateKey($domain);
    }

    public function storeUserSupplied(string $csr, string $domain): void
    {
        $this->cloudDisk->storeCsr($csr, $domain);
    }

    /**
     * Checks if the private key exists for a given domain.
     */
    public function hasPrivateKey(string $domain): bool
    {
        return $this->cloudDisk->hasPrivateKey($domain);
    }

    public function hasCsr(string $domain): bool
    {
        return $this->cloudDisk->hasCsr($domain);
    }

    /**
     * Get the contents of the private key file for a given domain.
     *
     * @throws FileNotFoundException
     */
    public function getPrivateKey(string $domain): string
    {
        return $this->cloudDisk->getPrivateKey($domain);
    }

    /**
     * Get the raw contents of the csr file for a given domain.
     *
     * @throws FileNotFoundException
     */
    public function getRawCsr(string $domain): string
    {
        return $this->cloudDisk->getCsr($domain);
    }

    /**
     * Save the root certificate for a domain to the filesystem.
     */
    public function saveCsr(string $domain, string $csr): void
    {
        $this->cloudDisk->storeCsr($csr, $domain);
    }

    /**
     * Update private key contents.
     */
    public function updatePrivateKey(string $domain, string $privateKey): bool
    {
        return $this->cloudDisk->storeKey($privateKey, $domain);
    }

    /**
     * Deletes the CSR and private key for a given domain.
     */
    public function delete(string $domain): void
    {
        $this->cloudDisk->deleteCsr($domain);
        $this->cloudDisk->deleteKey($domain);
    }

    /**
     * Get the decoded csr data for a given domain.
     */
    public function getCsrData(string $domain): CsrData
    {
        $csr = $this->cloudDisk->getCsr($domain);

        return $this->openSsl->getCsrData($csr);
    }

    public function removeLocalDirectory(string $domain): void
    {
        $this->localDisk->removeDirectory($domain);
    }

    private function storeCsr(string $domain): void
    {
        $csr = $this->localDisk->getCsr($domain);

        $this->cloudDisk->storeCsr($csr, $domain);
    }

    private function storePrivateKey(string $domain): void
    {
        $privateKey = $this->localDisk->getPrivateKey($domain);

        $this->cloudDisk->storeKey($privateKey, $domain);
    }
}
