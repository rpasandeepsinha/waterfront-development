<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages;

use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;

class Connection
{
    private ?string $apiUrl = null;

    private ?string $username = null;

    private ?string $password = null;

    private ?string $secretKey = null;

    /**
     * @throws PleskClientException
     */
    public function setApiUrl(string $apiUrl): void
    {
        if ($apiUrl === '') {
            throw PleskClientException::missingApiUrl();
        }

        $apiUrlValidated = filter_var($apiUrl, FILTER_VALIDATE_URL);
        if ($apiUrlValidated === false) {
            throw PleskClientException::invalidApiUrl($apiUrl);
        }

        $this->apiUrl = $apiUrl;
    }

    /**
     * @throws PleskClientException
     */
    public function setUsername(string $username): void
    {
        if ($username === '') {
            throw PleskClientException::InvalidArgumentException('The username is empty.');
        }

        $this->username = $username;
    }

    /**
     * @throws PleskClientException
     */
    public function setPassword(string $password): void
    {
        if ($password === '') {
            throw PleskClientException::InvalidArgumentException('The password is empty.');
        }

        $this->password = $password;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }
}
