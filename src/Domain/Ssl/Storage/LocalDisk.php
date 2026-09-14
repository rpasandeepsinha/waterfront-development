<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Storage;

use Illuminate\Support\Facades\File;
use UnexpectedValueException;

class LocalDisk
{
    public function __construct(
        private readonly string $disk,
    ) {
    }

    public function prepareDirectory(string $domain): void
    {
        $localPath = $this->getDirectoryPath($domain);

        if (! File::isDirectory($localPath)) {
            File::makeDirectory($localPath, 0755, true, true);
        }
    }

    public function removeDirectory(string $domain): void
    {
        File::deleteDirectory($this->getDirectoryPath($domain));
    }

    public function getPrivateKey(string $domain): string
    {
        return File::get($this->getPrivateKeyPath($domain));
    }

    public function getCsr(string $domain): string
    {
        return File::get($this->getCsrPath($domain));
    }

    public function getPrivateKeyPath(string $domain): string
    {
        self::assertValidDomain($domain);

        return $this->disk . '/' . $domain . '/' . $domain . '.key';
    }

    public function getCsrPath(string $domain): string
    {
        self::assertValidDomain($domain);

        return $this->disk . '/' . $domain . '/' . $domain . '.csr';
    }

    private function getDirectoryPath(string $domain): string
    {
        return $this->disk . '/' . $domain;
    }

    private function assertValidDomain(string $domain): void
    {
        if (
            ! str_contains($domain, '/')
            && ! str_contains($domain, '\\')
            && ! (bool) preg_match('#\s#', $domain)
            && $domain !== '.'
            && $domain !== '..'
        ) {
            return;
        }

        throw new UnexpectedValueException('"' . $domain . '" is not a valid name for a domain name');
    }
}
