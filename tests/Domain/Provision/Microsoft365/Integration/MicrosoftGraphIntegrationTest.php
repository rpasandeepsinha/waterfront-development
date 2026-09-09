<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Microsoft365\Integration;

use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use SandwaveIo\Microsoft\Graph\Models\Domain;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsMxRecord;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsRecordCollectionResponse;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsTxtRecord;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotCreatedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotFoundException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotVerifiedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\ServiceDnsRecordsNotFoundException;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365CreateDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetServiceDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365PromoteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365VerifyDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\CreateDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\GetDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\ServiceDnsRecordsResult;
use Waterfront\Domain\Provision\Microsoft365\Results\VerifyDomainResult;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Infra\Microsoft\Graph\Factory\GraphServiceClientFactory;

#[CoversClass(Microsoft365VerifyDomainRequest::class)]
#[CoversClass(Microsoft365GetDomainRequest::class)]
#[CoversClass(Microsoft365PromoteDomainRequest::class)]
#[CoversClass(Microsoft365CreateDomainRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
class MicrosoftGraphIntegrationTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-domain.nl';

    private const string TENANT_ID = '8e4f6848-6efa-48f2-9cd7-343cab7de6ff';

    private ProvisionGateway $gateway;

    private GraphServiceClient&MockInterface $graphClient;

    private UuidInterface $tagUuid;

    public function setUp(): void
    {
        parent::setUp();

        $this->tagUuid = Str::uuid();

        $graphClient = self::mock(GraphServiceClient::class);

        $mockGraphFactory = self::createStub(GraphServiceClientFactory::class);
        $mockGraphFactory

            ->method('createForAdmin')
            ->willReturn($graphClient);

        $mockGraphFactory

            ->method('createForCustomer')
            ->willReturn($graphClient);

        $this->app->bind(GraphServiceClientFactory::class, fn () => $mockGraphFactory);

        $this->graphClient = $graphClient;
        $this->gateway = $this->app->make(ProvisionGateway::class);
    }

    #[Test]
    public function getDomain(): void
    {
        $m365ApiResponse = new Domain();
        $m365ApiResponse->setId(self::DOMAIN);
        $m365ApiResponse->setIsVerified(true);
        $m365ApiResponse->setIsDefault(false);

        $this->graphClient
            ->shouldReceive('domains->byDomainId->get->wait')
            ->once()
            ->andReturn($m365ApiResponse);

        $request = new Microsoft365GetDomainRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(GetDomainResult::class, $result);
        self::assertNull($result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $exception = new DomainNotFoundException(self::DOMAIN);
        $this->graphClient
            ->shouldReceive('domains->byDomainId->get->wait')
            ->once()
            ->andThrow($exception);

        $request = new Microsoft365GetDomainRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(GetDomainResult::class, $result);
        self::assertSame($exception, $result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningRequest::class, 2);
        self::assertDatabaseCount(ProvisioningResult::class, 2);
    }

    #[Test]
    public function createDomain(): void
    {
        $m365ApiResponse = new Domain();
        $m365ApiResponse->setId(self::DOMAIN);
        $m365ApiResponse->setIsVerified(true);
        $m365ApiResponse->setIsDefault(true);

        $this->graphClient
            ->shouldReceive('domains->post->wait')
            ->once()
            ->andReturn($m365ApiResponse);

        $request = new Microsoft365CreateDomainRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(CreateDomainResult::class, $result);
        self::assertNull($result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(ProvisioningResult::class, 1);

        $exception = new DomainNotCreatedException(self::DOMAIN);

        $this->graphClient
            ->shouldReceive('domains->post->wait')
            ->once()
            ->andThrow($exception);

        $request = new Microsoft365CreateDomainRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(CreateDomainResult::class, $result);
        self::assertSame($exception, $result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningRequest::class, 2);
        self::assertDatabaseCount(ProvisioningResult::class, 2);
    }

    #[Test]
    public function verifyDomain(): void
    {
        $m365ApiResponse = new Domain();
        $m365ApiResponse->setId(self::DOMAIN);
        $m365ApiResponse->setIsVerified(true);

        $this->graphClient
            ->shouldReceive('domains->byDomainId->verify->post->wait')
            ->once()
            ->andReturn($m365ApiResponse);

        $request = new Microsoft365VerifyDomainRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(VerifyDomainResult::class, $result);
        self::assertNull($result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(ProvisioningResult::class, 1);

        $m365ApiResponse->setIsVerified(false);

        $this->graphClient
            ->shouldReceive('domains->byDomainId->verify->post->wait')
            ->once()
            ->andReturn($m365ApiResponse);

        $request = new Microsoft365VerifyDomainRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(VerifyDomainResult::class, $result);
        self::assertInstanceOf(DomainNotVerifiedException::class, $result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningResult::class, 2);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);
    }

    #[Test]
    public function serviceDnsRecord(): void
    {
        $txtRecord = new DomainDnsTxtRecord();
        $txtRecord->setText('test');

        $mxRecord = new DomainDnsMxRecord();
        $mxRecord->setMailExchange('10 mail.test.nl.');

        $m365ApiResponse = new DomainDnsRecordCollectionResponse();
        $m365ApiResponse->setValue([
            $txtRecord,
            $mxRecord,
        ]);

        $this->graphClient
            ->shouldReceive('domains->byDomainId->serviceConfigurationRecords->get->wait')
            ->once()
            ->andReturn($m365ApiResponse);

        $request = new Microsoft365GetServiceDnsRecordsRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(ServiceDnsRecordsResult::class, $result);
        self::assertNull($result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(ProvisioningResult::class, 1);

        $m365ApiResponse->setValue(null);

        $this->graphClient
            ->shouldReceive('domains->byDomainId->serviceConfigurationRecords->get->wait')
            ->once()
            ->andReturn($m365ApiResponse);

        $request = new Microsoft365GetServiceDnsRecordsRequest(self::DOMAIN, Uuid::fromString(self::TENANT_ID), $this->tagUuid);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(ServiceDnsRecordsResult::class, $result);
        self::assertInstanceOf(ServiceDnsRecordsNotFoundException::class, $result->exception);
        self::assertNull($result->validationResult);

        self::assertDatabaseCount(ProvisioningRequest::class, 2);
        self::assertDatabaseCount(ProvisioningResult::class, 2);
    }
}
