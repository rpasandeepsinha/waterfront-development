<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient\Messages;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\Messages\DomainHandleRequest;

#[CoversClass(DomainHandleRequest::class)]
class DomainHandleRequestTest extends TestCase
{
    #[Test]
    public function handleParametersConvertLocale(): void
    {
        $handleParameters = new HandleParameters();
        $handleParameters->setLocale('en-US');

        $request = new DomainHandleRequest(
            self::createStub(Client::class),
            self::createStub(OpenProviderConnectionInterface::class),
        );
        $request->setParameters($handleParameters);

        self::assertSame('en_US', $handleParameters->getLocale());
    }
}
