<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services;

use RuntimeException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyCreate\Result as SecretKeyCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyGet\Result as SecretKeyGetResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SecretKeyInterface;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Configuration\ConfigurationInterface;

/**
 * Internal service for getting the secret key for a server.
 */
class SecretKeyService
{
    public function __construct(
        private readonly SecretKeyInterface $secretKeyClient,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    /**
     * Set a specific target server for the hosting.
     *
     * @param string[]|null $credentials
     */
    public function setServer(Server $server, ?array $credentials = null): bool
    {
        return $this->secretKeyClient->setServer($server, $credentials);
    }

    /**
     * Gets the secret key from Plesk for the server ip. If it does not already exist it will be created.
     *
     * @param string[]|null $credentials
     *
     * @throws RuntimeException
     */
    public function getPleskSecretKey(Server $server, ?array $credentials = null): string
    {
        if (! $this->setServer($server, $credentials)) {
            return 'N/A';
        }

        $getKeysResult = $this->secretKeyClient->getSecretKeys();
        if ($getKeysResult->getStatus() !== SecretKeyGetResult::STATUS_OK) {
            throw new RuntimeException(
                'Error getting secret keys from Plesk: ' . $getKeysResult->getErrorMessage(),
                $getKeysResult->getErrorCode()
            );
        }

        $keys = $getKeysResult->getSecretKeys();

        $ipAddress = $this->configuration->getAsString('hostingservice.external_ip');
        if (array_key_exists($ipAddress, $keys)) {
            return $keys[$ipAddress];
        }

        return $this->createPleskSecretKey();
    }

    private function createPleskSecretKey(): string
    {
        $ipAddress = $this->configuration->getAsString('hostingservice.external_ip');

        $createKeyResult = $this->secretKeyClient->createSecretKey($ipAddress);

        if ($createKeyResult->getStatus() !== SecretKeyCreateResult::STATUS_OK) {
            throw new RuntimeException(
                'Error creating a secret key in Plesk: ' . $createKeyResult->getErrorMessage(),
                $createKeyResult->getErrorCode()
            );
        }

        return $createKeyResult->getSecretKey();
    }
}
