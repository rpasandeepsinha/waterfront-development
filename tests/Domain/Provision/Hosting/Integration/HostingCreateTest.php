<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Hosting\Integration;

use Illuminate\Support\Str;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\UuidInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingProviderException;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;

#[CoversClass(HostingCreateRequest::class)]
class HostingCreateTest extends IntegrationTestCase
{
    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Str::uuid();

        $provisionTraceabilityService = self::createStub(ProvisionTraceabilityService::class);
        $this->app->bind(ProvisionTraceabilityService::class, fn () => $provisionTraceabilityService);
    }

    #[Test]
    public function hostingCreateValidationPlesk(): void
    {
        $validateFactory = $this->mock(Factory::class);
        $this->app->bind(Factory::class, fn () => $validateFactory);
        $validateFactory
            ->shouldReceive('make')
            ->once()
            ->andReturnUsing(
                fn (array $data, array $rules) => new Validator($this->createStub(Translator::class), $data, $rules),
            );

        $create = new HostingCreateRequest(
            servicePlan: 'test',
            email: 'test@kees.nl',
            context: $this->context,
        );

        self::assertSame(ProvisionType::HOSTING, $create->type);
        self::assertNull($create->provider);

        $provisionService = self::resolve(ProvisionGateway::class);
        $result = $provisionService->request($create);

        self::assertInstanceOf(AbstractProvisionResult::class, $result);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertNotNull($result->validationResult);
        self::assertArrayHasKey('contactName', $result->validationResult->messages);
        self::assertArrayHasKey('ipv4', $result->validationResult->messages);
        self::assertArrayNotHasKey('servicePLan', $result->validationResult->messages);
        self::assertArrayNotHasKey('email', $result->validationResult->messages);
        self::assertArrayNotHasKey('enableDns', $result->validationResult->messages);
        self::assertArrayNotHasKey('enableFtp', $result->validationResult->messages);
    }

    #[Test]
    public function hostingCreateValidationDirectAdmin(): void
    {
        $validateFactory = $this->mock(Factory::class);
        $this->app->bind(Factory::class, fn () => $validateFactory);
        $validateFactory
            ->shouldReceive('make')
            ->once()
            ->andReturnUsing(
                fn (array $data, array $rules) => new Validator($this->createStub(Translator::class), $data, $rules),
            );

        $create = new HostingCreateRequest(
            servicePlan: 'test',
            email: 'test@kees.nl',
            context: $this->context,
            enableDns: true,
            enableFtp: true,
        );

        $create->provider = ProvisionProvider::DIRECTADMIN;

        self::assertSame(ProvisionType::HOSTING, $create->type);

        $provisionService = self::resolve(ProvisionGateway::class);
        $result = $provisionService->request($create);

        self::assertInstanceOf(AbstractProvisionResult::class, $result);
        self::assertSame(ProvisionStatus::PENDING, $result->provisionStatus);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function hostingCreateUnknownProvider(): void
    {
        $testProvider = ProvisionProvider::RTR;

        $create = new HostingCreateRequest('test', 'test@kees.nl', $this->context);
        $create->provider = $testProvider;
        $expectedErrorMessage = sprintf(
            "Can't resolve hosting service from unknown provider [%s]",
            $testProvider->value,
        );

        $provisionService = self::resolve(ProvisionGateway::class);
        $result = $provisionService->request($create);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(UnknownHostingProviderException::class, $result->exception);
        self::assertSame($expectedErrorMessage, $result->exception->getMessage());
    }
}
