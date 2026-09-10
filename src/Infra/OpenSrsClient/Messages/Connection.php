<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use RuntimeException;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;

class Connection implements OpenSrsConnectionInterface
{
    private string $apiUrl;

    private string $username;

    private string $apiKey;

    /**
     * @throws RuntimeException
     */
    public function __construct(string $apiUrl, string $username, string $apiKey)
    {
        $this->setApiUrl($apiUrl);
        $this->setUsername($username);
        $this->setApiKey($apiKey);
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @throws RuntimeException
     */
    private function setApiUrl(string $apiUrl): void
    {
        if ($apiUrl === '') {
            throw new RuntimeException('The OpenSRS api url is missing.');
        }
        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('The OpenSRS api url is invalid: ' . $apiUrl);
        }

        $this->apiUrl = $apiUrl;
    }

    /**
     * @throws RuntimeException
     */
    private function setUsername(string $username): void
    {
        if ($username === '') {
            throw new RuntimeException('The username for OpenSRS is missing.');
        }
        $this->username = $username;
    }

    /**
     * @throws RuntimeException
     */
    private function setApiKey(string $apiKey): void
    {
        if ($apiKey === '') {
            throw new RuntimeException('The api key for OpenSRS is missing.');
        }
        $this->apiKey = $apiKey;
    }
}
