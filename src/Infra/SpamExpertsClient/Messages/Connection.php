<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Messages;

use RuntimeException;

class Connection
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

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCredentials(): string
    {
        return base64_encode($this->getUsername() . ':' . $this->getPassword());
    }

    private function setApiUrl(string $apiUrl): void
    {
        if ($apiUrl === '') {
            throw new RuntimeException('The api url is missing.');
        }
        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('The api url is invalid: ' . $apiUrl);
        }

        $this->apiUrl = $apiUrl;
    }

    private function setUsername(string $username): void
    {
        if ($username === '') {
            throw new RuntimeException('The username is missing.');
        }
        $this->username = $username;
    }

    private function setPassword(string $password): void
    {
        if ($password === '') {
            throw new RuntimeException('The password is missing.');
        }
        $this->password = $password;
    }
}
