<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\PleskClient;

#[CoversClass(PleskClient::class)]
class GetServerSsoUrlTest extends IntegrationTestCase
{
    #[Test]
    public function serverSsoException(): void
    {
        $pleskClient = self::resolve(PleskClient::class);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('You cannot login with sso into a Plesk server.');

        $pleskClient->getServerSsoUrl();
    }
}
