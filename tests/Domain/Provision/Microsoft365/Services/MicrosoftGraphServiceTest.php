<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Microsoft365\Services;

use Exception;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\Microsoft\Graph\Domains\Item\Promote\PromotePostResponse;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use SandwaveIo\Microsoft\Graph\Models\Domain;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotCreatedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotFoundException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotPromotedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotVerifiedException;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365CreateDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365PromoteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365VerifyDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Services\MicrosoftGraphService;
use Waterfront\Infra\Microsoft\Graph\Factory\GraphServiceClientFactory;

#[CoversClass(MicrosoftGraphService::class)]
class MicrosoftGraphServiceTest extends TestCase
{
    private const string DOMAIN_NAME = 'testdomain.com';

    private const string TENANT_ID = '8e4f6848-6efa-48f2-9cd7-343cab7de6ff';

    private GraphServiceClient&MockInterface $mockGraphServiceClient;

    private GraphServiceClientFactory&Stub $graphServiceClientFactory;

    private UuidInterface $tagUuid;

    public function setUp(): void
    {
        parent::setUp();

        $this->tagUuid = Str::uuid();

        $graphMock = self::mock(GraphServiceClient::class);

        $this->graphServiceClientFactory = self::createStub(GraphServiceClientFactory::class);
        $this->graphServiceClientFactory
            ->method('createForCustomer')
            ->willReturn($graphMock);

        $this->graphServiceClientFactory
            ->method('createForAdmin')
            ->willReturn($graphMock);

        $this->mockGraphServiceClient = $graphMock;
    }

    #[Test]
    public function getDomain(): void
    {
        $domainModel = new Domain();
        $domainModel->setId(self::DOMAIN_NAME);

        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->get->wait')
            ->andReturn($domainModel);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->getDomain(new Microsoft365GetDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertEquals($domainModel, $result->domain);
        self::assertNull($result->validationResult);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getDomainFailedWhenNull(): void
    {
        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->get->wait')
            ->andReturn(null);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->getDomain(new Microsoft365GetDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DomainNotFoundException::class, $result->exception);
        self::assertNull($result->validationResult);
        self::assertNull($result->domain);
    }

    #[Test]
    public function getDomainFailedWhenExceptionOccurs(): void
    {
        $testException = new Exception('Generic exception');
        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->get->wait')
            ->andThrow($testException);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->getDomain(new Microsoft365GetDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertEquals($testException, $result->exception);
        self::assertNull($result->domain);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function createDomain(): void
    {
        $domainModel = new Domain();
        $domainModel->setId(self::DOMAIN_NAME);

        $this->mockGraphServiceClient
            ->shouldReceive('domains->post->wait')
            ->andReturn($domainModel);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->createDomain(new Microsoft365CreateDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($domainModel, $result->domain);
        self::assertNull($result->validationResult);
        self::assertNull($result->exception);
    }

    #[Test]
    public function createDomainFailedWhenNull(): void
    {
        $this->mockGraphServiceClient
            ->shouldReceive('domains->post->wait')
            ->andReturn(null);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->createDomain(new Microsoft365CreateDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DomainNotCreatedException::class, $result->exception);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function createDomainFailedWhenExceptionOccurs(): void
    {
        $testException = new Exception('Generic exception');
        $this->mockGraphServiceClient
            ->shouldReceive('domains->post->wait')
            ->andThrow($testException);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->createDomain(new Microsoft365CreateDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertEquals($testException, $result->exception);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function promoteDomain(): void
    {
        $promoteResponse = new PromotePostResponse();
        $promoteResponse->setValue(true);

        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->promote->post->wait')
            ->andReturn($promoteResponse);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->promoteDomain(new Microsoft365PromoteDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertTrue($result->isPromoted);
        self::assertNull($result->validationResult);
        self::assertNull($result->exception);
    }

    #[Test]
    public function promoteDomainFailedWhenNull(): void
    {
        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->promote->post->wait')
            ->andReturn(null);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->promoteDomain(new Microsoft365PromoteDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DomainNotPromotedException::class, $result->exception);
        self::assertNull($result->validationResult);
        self::assertFalse($result->isPromoted);
    }

    #[Test]
    public function promoteDomainFailedWhenPromotedFalse(): void
    {
        $promoteResponse = new PromotePostResponse();
        $promoteResponse->setValue(false);

        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->promote->post->wait')
            ->andReturn($promoteResponse);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->promoteDomain(new Microsoft365PromoteDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DomainNotPromotedException::class, $result->exception);
        self::assertNull($result->validationResult);
        self::assertFalse($result->isPromoted);
    }

    #[Test]
    public function promoteDomainFailedWhenExceptionOccurs(): void
    {
        $testException = new Exception('Generic exception');
        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->promote->post->wait')
            ->andThrow($testException);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->promoteDomain(new Microsoft365PromoteDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertEquals($testException, $result->exception);
        self::assertNull($result->validationResult);
        self::assertFalse($result->isPromoted);
    }

    #[Test]
    public function verifyDomain(): void
    {
        $domainModel = new Domain();
        $domainModel->setIsVerified(true);

        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->verify->post->wait')
            ->andReturn($domainModel);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->verifyDomain(new Microsoft365VerifyDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNotNull($result->domain);
        self::assertTrue($result->domain->getIsVerified());
        self::assertNull($result->validationResult);
        self::assertNull($result->exception);
    }

    #[Test]
    public function verifyDomainFailedWhenNull(): void
    {
        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->verify->post->wait')
            ->andReturn(null);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->verifyDomain(new Microsoft365VerifyDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DomainNotVerifiedException::class, $result->exception);
        self::assertNull($result->validationResult);
        self::assertNull($result->domain);
    }

    #[Test]
    public function verifyDomainFailedWhenVerifiedFalse(): void
    {
        $domainModel = new Domain();
        $domainModel->setIsVerified(false);

        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->verify->post->wait')
            ->andReturn($domainModel);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->verifyDomain(new Microsoft365VerifyDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DomainNotVerifiedException::class, $result->exception);
        self::assertNull($result->validationResult);
        self::assertNull($result->domain);
    }

    #[Test]
    public function verifyDomainFailedWhenExceptionOccurs(): void
    {
        $testException = new Exception('Generic exception');
        $this->mockGraphServiceClient
            ->shouldReceive('domains->byDomainId->verify->post->wait')
            ->andThrow($testException);

        $service = new MicrosoftGraphService($this->graphServiceClientFactory);

        $result = $service->verifyDomain(new Microsoft365VerifyDomainRequest(self::DOMAIN_NAME, Uuid::fromString(self::TENANT_ID), $this->tagUuid));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertEquals($testException, $result->exception);
        self::assertNull($result->validationResult);
        self::assertNull($result->domain);
    }
}
