<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

class CsrValidationValueGenerator
{
    public function getCnameValidationHost(string $csr): string
    {
        return sprintf('_%s', $this->getMd5HashFromCsr($csr));
    }

    public function getCnameValidationValue(string $csr): string
    {
        $sha = $this->getSha256HashFromCsr($csr);
        return sprintf('%s.sectigo.com.', substr_replace($sha, '.', 32, 0));
    }

    public function getFileValidationFileName(string $csr): string
    {
        return sprintf('%s.txt', strtoupper($this->getMd5HashFromCsr($csr)));
    }

    public function getFileValidationValue(string $csr): string
    {
        return sprintf('%s sectigo.com', $this->getSha256HashFromCsr($csr));
    }

    private function getMd5HashFromCsr(string $csr): string
    {
        return \hash('md5', $this->getBinaryCsr($csr));
    }

    private function getSha256HashFromCsr(string $csr): string
    {
        return \hash('sha256', $this->getBinaryCsr($csr));
    }

    private function getBinaryCsr(string $csr): string
    {
        $data = \base64_decode(
            \str_replace([
                '-----BEGIN CERTIFICATE REQUEST-----',
                '-----END CERTIFICATE REQUEST-----',
                '-----BEGIN NEW CERTIFICATE REQUEST-----',
                '-----END NEW CERTIFICATE REQUEST-----',
            ], '', $csr),
            true
        );
        assert($data !== false);
        return $data;
    }
}
