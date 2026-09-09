<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(LogContextBuilder::class)]
#[CoversClass(ProvisionRequest::class)]
class LogContextBuilderTest extends TestCase
{
    #[Test]
    public function forReturnsDefaultLogContextFromRequest(): void
    {
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 42;

        $built = LogContextBuilder::for($request)->build();

        self::assertSame(
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                LoggingContextKeys::PROVISIONING_REQUEST_ID => 42,
                LoggingContextKeys::PROVISIONING_CONTEXT => $context,
            ],
            $built
        );
    }

    #[Test]
    public function withExceptionAddsExceptionToContext(): void
    {
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 1;

        $exception = new RuntimeException('boom');

        $built = LogContextBuilder::for($request)
            ->withException($exception)
            ->build();

        self::assertSame($exception, $built[LoggingContextKeys::EXCEPTION]);
    }

    #[Test]
    public function withMetaAddsMetaToContext(): void
    {
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 1;

        $built = LogContextBuilder::for($request)
            ->withMeta(['caddy_id' => 'abc123'])
            ->build();

        self::assertSame(['caddy_id' => 'abc123'], $built[LoggingContextKeys::META]);
    }

    #[Test]
    public function withAddsArbitraryKeyToContext(): void
    {
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 1;

        $built = LogContextBuilder::for($request)
            ->with(LoggingContextKeys::CUSTOMER_ID, 99)
            ->build();

        self::assertSame(99, $built[LoggingContextKeys::CUSTOMER_ID]);
    }

    #[Test]
    public function builderIsChainable(): void
    {
        $context = Uuid::uuid4();
        $request = new CreateRedirectRequest(
            domain: 'example.com',
            destinationUrl: 'https://destination.example.com',
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $request->requestId = 7;

        $exception = new RuntimeException('boom');

        $built = LogContextBuilder::for($request)
            ->withException($exception)
            ->withMeta(['caddy_id' => 'abc'])
            ->with(LoggingContextKeys::DOMAIN_NAME, 'example.com')
            ->build();

        self::assertSame(
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                LoggingContextKeys::PROVISIONING_REQUEST_ID => 7,
                LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => ['caddy_id' => 'abc'],
                LoggingContextKeys::DOMAIN_NAME => 'example.com',
            ],
            $built
        );
    }
}
