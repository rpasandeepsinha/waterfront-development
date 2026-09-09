<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\CsrPersistanceStrategies;

use OpenSSLAsymmetricKey;
use RuntimeException;
use Waterfront\Domain\Ssl\Models\CsrData;
use Waterfront\Domain\Ssl\Models\CsrSubjectData;

class OpenSslExtensionStrategy
{
    public function createAndStorePrivateKeyAndCsr(
        CsrSubjectData $subjectData,
        int $encryptionStrength,
        string $privateKeyPath,
        string $csrPath
    ): void {
        $privateKeyResource = $this->createPrivateKey($encryptionStrength, $privateKeyPath);
        $this->createCsr($subjectData->toArray(), $privateKeyResource, $csrPath);
    }

    public function getCsrData(string $csrPath): CsrData
    {
        $subjectDataArray = openssl_csr_get_subject($csrPath);

        if ($subjectDataArray === false) {
            throw new RuntimeException('Subject data could not be parsed from the CSR. Raw csr data: ' . $csrPath);
        }

        $subjectData = new CsrSubjectData(
            $subjectDataArray['CN'],
            $subjectDataArray['O'],
            $subjectDataArray['OU'],
            $subjectDataArray['L'],
            $subjectDataArray['ST'],
            $subjectDataArray['C']
        );

        $publicKey = openssl_csr_get_public_key($csrPath);
        if ($publicKey === false) {
            throw new RuntimeException('Public key could not be parsed from the CSR. Raw csr data: ' . $csrPath);
        }

        $publicKeyDetails = openssl_pkey_get_details($publicKey);
        if ($publicKeyDetails === false) {
            throw new RuntimeException('Details could not be parsed from the public key. Raw csr data: ' . $csrPath);
        }

        $encryptionStrength = $publicKeyDetails['bits'];
        $publicKeyAlgorithm = 'unknown';
        if (OPENSSL_KEYTYPE_RSA == $publicKeyDetails['type']) {
            $publicKeyAlgorithm = 'rsaEncryption';
        }

        return new CsrData($subjectData, $publicKeyAlgorithm, $encryptionStrength);
    }

    /**
     * Create a private key and put it in a file.
     */
    private function createPrivateKey(int $encryptionStrength, string $privateKeyPath): OpenSSLAsymmetricKey
    {
        $config = [
            'private_key_bits' => $encryptionStrength,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);

        if (file_exists($privateKeyPath)) {
            unlink($privateKeyPath);
        }

        if ($privateKey === false) {
            throw new RuntimeException(strval(openssl_error_string()));
        }

        if (! openssl_pkey_export_to_file($privateKey, $privateKeyPath)) {
            throw new RuntimeException('Export to private file failure: ' . openssl_error_string());
        }

        return $privateKey;
    }

    /**
     * Create a CSR and put it in a file.
     *
     * @param array<string, string> $subjectData
     */
    private function createCsr(array $subjectData, OpenSSLAsymmetricKey $privateKeyResource, string $csrPath): void
    {
        $config = [
            'digest_alg' => 'sha256',
        ];

        if (file_exists($csrPath)) {
            unlink($csrPath);
        }

        $csr = openssl_csr_new($subjectData, $privateKeyResource, $config);

        if ($csr === false) {
            throw new RuntimeException((string) openssl_error_string());
        }

        if (! openssl_csr_export_to_file($csr, $csrPath)) {
            throw new RuntimeException('Export to csr file failure: ' . openssl_error_string());
        }
    }
}
