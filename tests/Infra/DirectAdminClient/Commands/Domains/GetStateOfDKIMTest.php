<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Domains;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\GetEmail;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(GetEmail::class)]
class GetStateOfDKIMTest extends DirectAdminTestCase
{
    private GetEmail $getStateOfDkim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getStateOfDkim = new GetEmail('default.nl');
    }

    #[Test]
    public function dkimEnabled(): void
    {
        $response = ['DEFAULT_POP_QUOTA' => '50', 'DKIM' => '1', 'DKIM_ENABLED' => '1'];

        $this->getStateOfDkim->responseReceived($response);

        self::assertTrue($this->getStateOfDkim->hasSucceeded());
        self::assertTrue($this->getStateOfDkim->getDkimEnabled());
    }

    #[Test]
    public function dkimEnabledNotFound(): void
    {
        $response = ['DEFAULT_POP_QUOTA' => '50', 'DKIM' => '1'];

        $error = 'Cannot retrieve dkim enabled for domain DKIM_ENABLED. Response is missing the data; {"DEFAULT_POP_QUOTA":"50","DKIM":"1"}';
        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs($error);

        $this->getStateOfDkim->responseReceived($response);

        self::assertTrue($this->getStateOfDkim->hasSucceeded());
        self::assertTrue($this->getStateOfDkim->getDkimEnabled());
    }
}
