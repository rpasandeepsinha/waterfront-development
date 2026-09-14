<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Storage;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use UnexpectedValueException;

class KeyCloud
{
    public function __construct(
        private readonly string $cryptoKey,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function storeCsr(string $csr, string $domain): bool
    {
        return $this->filesystem->put(
            $this->getCsrPath($domain),
            $csr,
        );
    }

    /**
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     */
    public function storeKey(string $key, string $domain): bool
    {
        $cryptoKey = Key::loadFromAsciiSafeString($this->cryptoKey);
        $encrypted = Crypto::encrypt($key, $cryptoKey);

        return $this->filesystem->put(
            $this->getEncryptedKeyPath($domain),
            $encrypted,
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function getCsr(string $domain): string
    {
        $path = $this->getCsrPath($domain);

        if (! $this->hasCsr($domain)) {
            throw new FileNotFoundException($path);
        }

        return $this->filesystem->get($path) ?? '';
    }

    /**
     * @throws FileNotFoundException
     * @throws EnvironmentIsBrokenException
     * @throws BadFormatException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function getPrivateKey(string $domain): string
    {
        if ($this->hasEncryptedPrivateKey($domain)) {
            $key = $this->filesystem->get($this->getEncryptedKeyPath($domain)) ?? '';

            return $this->decrypt($key);
        }

        if ($this->hasPlainTextPrivateKey($domain)) {
            return $this->filesystem->get($this->getPrivateKeyPath($domain)) ?? '';
        }

        throw new FileNotFoundException($this->getPrivateKeyPath($domain));
    }

    public function hasPrivateKey(string $domain): bool
    {
        return $this->hasPlainTextPrivateKey($domain) || $this->hasEncryptedPrivateKey($domain);
    }

    public function hasCsr(string $domain): bool
    {
        return $this->filesystem->exists($this->getCsrPath($domain));
    }

    public function deleteCsr(string $domain): bool
    {
        return $this->hasCsr($domain) && $this->filesystem->delete($this->getCsrPath($domain));
    }

    public function deleteKey(string $domain): bool
    {
        if ($this->hasEncryptedPrivateKey($domain)) {
            $path = $this->getEncryptedKeyPath($domain);

            return $this->filesystem->delete($path);
        }

        if ($this->hasPlainTextPrivateKey($domain)) {
            $path = $this->getPrivateKeyPath($domain);

            return $this->filesystem->delete($path);
        }

        return false;
    }

    private function hasPlainTextPrivateKey(string $domain): bool
    {
        return $this->filesystem->exists($this->getPrivateKeyPath($domain));
    }

    private function hasEncryptedPrivateKey(string $domain): bool
    {
        return $this->filesystem->exists($this->getEncryptedKeyPath($domain));
    }

    private function getCsrPath(string $domain): string
    {
        self::assertValidDomain($domain);

        return $domain . '/' . $domain . '.csr';
    }

    private function getEncryptedKeyPath(string $domain): string
    {
        return $this->getPrivateKeyPath($domain) . '.crypt';
    }

    private function getPrivateKeyPath(string $domain): string
    {
        self::assertValidDomain($domain);

        return $domain . '/' . $domain . '.key';
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

    /**
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     * @throws BadFormatException
     */
    private function decrypt(string $ciphertext): string
    {
        $cryptoKey = Key::loadFromAsciiSafeString($this->cryptoKey);

        return Crypto::decrypt($ciphertext, $cryptoKey);
    }
}
