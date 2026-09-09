<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use RuntimeException;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;

class Connection implements OpenProviderConnectionInterface
{
    private string $apiUrl;

    private string $username;

    private string $password;

    /**
     * @throws RuntimeException
     */
    public function __construct(string $apiUrl, string $username, string $password)
    {
        $this->setApiUrl($apiUrl);
        $this->setUsername($username);
        $this->setPassword($password);
    }

    /**
     * {@inheritDoc}
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * {@inheritDoc}
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @throws RuntimeException
     */
    private function setApiUrl(string $apiUrl): void
    {
        if ($apiUrl === '') {
            throw new RuntimeException('The open provider api url is missing.');
        }
        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('The open provider api url is invalid: ' . $apiUrl);
        }

        $this->apiUrl = $apiUrl;
    }

    /**
     * @throws RuntimeException
     */
    private function setUsername(string $username): void
    {
        if ($username === '') {
            throw new RuntimeException('The username for open provider is missing.');
        }
        $this->username = $username;
    }

    /**
     * @throws RuntimeException
     */
    private function setPassword(string $password): void
    {
        if ($password === '') {
            throw new RuntimeException('The password for open provider is missing.');
        }
        $this->password = $password;
    }
}
