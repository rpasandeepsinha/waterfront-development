<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\Connection;

#[CoversClass(Connection::class)]
class ConnectionTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection();
    }

    #[Test]
    public function setApiUrlGetSuccess(): void
    {
        self::assertNull($this->connection->getApiUrl());
        $apiUrl = 'https://api.nl';
        $this->connection->setApiUrl($apiUrl);
        self::assertSame($apiUrl, $this->connection->getApiUrl());
    }

    #[Test]
    public function setApiUrlFailedMissing(): void
    {
        self::assertNull($this->connection->getApiUrl());
        $apiUrl = '';
        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('The API url is missing.');
        $this->connection->setApiUrl($apiUrl);
    }

    #[Test]
    public function setApiUrlFailedInvalidUrl(): void
    {
        self::assertNull($this->connection->getApiUrl());
        $apiUrl = 'invalid url';
        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('The API url invalid url is invalid.');
        $this->connection->setApiUrl($apiUrl);
    }

    #[Test]
    public function emptyUsername(): void
    {
        self::assertNull($this->connection->getUsername());
        $username = '';
        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('The username is empty.');
        $this->connection->setUsername($username);
    }

    #[Test]
    public function emptyPassword(): void
    {
        self::assertNull($this->connection->getPassword());
        $password = '';
        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('The password is empty.');
        $this->connection->setPassword($password);
    }
}
