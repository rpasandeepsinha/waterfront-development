<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\DomainNames\Coupling\Integration;

use Illuminate\Support\Str;
use Mockery;  // @phpstan-ignore-line disallowed.namespace
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\UuidInterface;
use Tests\Factories\DomainNameCoupleDeploymentFactory;
use Tests\Factories\RedirectDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\DomainNames\Coupling\Interfaces\DomainNameCoupleInterface;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\DomainNames\Coupling\Repositories\DomainNameCoupleRepository;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameCoupleResult;
use Waterfront\Domain\Provision\DomainNames\Coupling\Rules\DomainNameCoupleAllowedRule;
use Waterfront\Domain\Provision\DomainNames\Coupling\Services\DomainNameCoupleService;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Services\HostingProvisionService;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Results\ProvisionResult;

#[CoversClass(ProvisionGateway::class)]
#[CoversClass(DomainNameCoupleRepository::class)]
#[CoversClass(DomainNameCoupleRequest::class)]
#[CoversClass(DomainNameDecoupleRequest::class)]
#[CoversClass(DomainNameCoupleService::class)]
class DomainNameCoupleIntegrationTest extends IntegrationTestCase
{
    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Str::uuid();
    }

    #[Test]
    public function coupleToRedirect(): void
    {
        $domain = 'test.nl';
        $redirectDeployment = RedirectDeploymentFactory::new()->createOne();
        $this->allowCouplingToRedirect();

        $coupleRequest = new DomainNameCoupleRequest(
            domain: $domain,
            requestUuid: $redirectDeployment->request->uuid,
            context: $this->context,
        );

        $mockResult = new DomainNameCoupleResult(
            provisionData: $coupleRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
            domain: $domain,
        );

        /**
         * Mock the supported type service to simulate the coupling process.
         * We need to use mockery to mock the service as it implements the
         * DomainNameCoupleInterface, which is required for the coupling.
         *
         * @phpstan-ignore disallowed.namespace
         */
        $coupleServiceMock = Mockery::mock(HostingProvisionService::class, DomainNameCoupleInterface::class);

        $coupleServiceMock->shouldReceive('coupleToDomainName')
            ->once()
            ->withArgs(fn (DomainNameCoupleRequest $request) => $request->domain === $domain && $request->requestUuid->toString() === $redirectDeployment->request->uuid->toString())
            ->andReturn($mockResult);

        $this->app->bind(HostingProvisionService::class, fn () => $coupleServiceMock);

        $gateway = $this->app->make(ProvisionGateway::class);
        $result = $gateway->request($coupleRequest);

        $storedRequest = ProvisioningRequest::findOrFail($coupleRequest->requestId);

        self::assertSame(ProvisionType::DOMAIN_NAME_COUPLING, $storedRequest->request_type);
        self::assertSame(ProvisionRequestName::COUPLE_DOMAIN, $storedRequest->request_name);

        /** @var array{requestUuid: string, domain: string} $storedData */
        $storedData = json_decode($storedRequest->request_data, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($redirectDeployment->request->uuid->toString(), $storedData['requestUuid']);
        self::assertSame($domain, $storedData['domain']);

        self::assertSame($mockResult, $result);

        self::assertDatabaseHas(DomainNameCoupleDeployment::class, [
            'domain' => $domain,
            'couple_type' => ProvisionType::REDIRECT->value,
            'deployment_uuid' => $redirectDeployment->uuid->toString(),
            'origin_provisioning_request_id' => $coupleRequest->requestId,
        ]);
    }

    #[Test]
    public function decoupleToRedirect(): void
    {
        $domain = 'test.nl';
        $redirectDeployment = RedirectDeploymentFactory::new()->createOne();
        $coupleType = ProvisionType::REDIRECT;
        $this->allowCouplingToRedirect();

        $domainNameCoupleDeployment = DomainNameCoupleDeploymentFactory::new()
            ->redirectCoupling()
            ->createOne([
                'domain' => $domain,
                'couple_type' => $coupleType->value,
                'deployment_uuid' => $redirectDeployment->uuid->toString(),
            ]);

        $mockResult = self::mock(ProvisionResult::class);
        $mockResult->provisionStatus = ProvisionStatus::SUCCESS;
        $mockResult->exception = null;
        $mockResult->validationResult = null;

        /**
         * Mock a supported couple service to simulate the coupling process.
         * We need to use mockery to mock the service as it implements the
         * DomainNameCoupleInterface, which is required for the coupling.
         *
         * @phpstan-ignore disallowed.namespace
         */
        $coupleServiceMock = Mockery::mock(HostingProvisionService::class, DomainNameCoupleInterface::class);

        $coupleServiceMock->shouldReceive('decoupleDomainName')
            ->once()
            ->withArgs(fn (DomainNameDecoupleRequest $request) => $request->domain === $domain && $request->requestUuid->toString() === $redirectDeployment->request->uuid->toString())
            ->andReturn($mockResult);

        $this->app->bind(HostingProvisionService::class, fn () => $coupleServiceMock);

        $decoupleRequest = new DomainNameDecoupleRequest(
            domain: $domain,
            requestUuid: $redirectDeployment->request->uuid,
            context: $this->context,
        );

        $gateway = $this->app->make(ProvisionGateway::class);
        $result = $gateway->request($decoupleRequest);

        $storedRequest = ProvisioningRequest::findOrFail($decoupleRequest->requestId);

        self::assertSame(ProvisionType::DOMAIN_NAME_COUPLING, $storedRequest->request_type);
        self::assertSame(ProvisionRequestName::DECOUPLE_DOMAIN, $storedRequest->request_name);

        /** @var array{requestUuid: string, domain: string} $storedData */
        $storedData = json_decode($storedRequest->request_data, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($redirectDeployment->request->uuid->toString(), $storedData['requestUuid']);
        self::assertSame($domain, $storedData['domain']);

        self::assertSame($mockResult, $result);

        self::assertSoftDeleted(DomainNameCoupleDeployment::class, [
            'domain' => $domain,
            'couple_type' => ProvisionType::REDIRECT->value,
            'deployment_uuid' => $redirectDeployment->uuid->toString(),
            'origin_provisioning_request_id' => $domainNameCoupleDeployment->origin_provisioning_request_id,
        ]);
    }

    private function allowCouplingToRedirect(): void
    {
        $mockDomainNameCoupleRule = self::mock(DomainNameCoupleAllowedRule::class);
        $mockDomainNameCoupleRule
            ->shouldReceive('validate')
            ->once()
            ->andReturnNull();

        $this->app->bind(DomainNameCoupleAllowedRule::class, fn () => $mockDomainNameCoupleRule);
    }
}
