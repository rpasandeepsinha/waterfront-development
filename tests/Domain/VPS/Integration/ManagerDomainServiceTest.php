<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Integration;

use ArrayIterator;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Email\Jobs\SendEmail;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Jobs\DeleteDomainJob;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Services\ManagerDomainService;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\CloudStackPaginationIterator;
use Waterfront\Infra\CloudStackClient\DTO\Account;
use Waterfront\Infra\CloudStackClient\DTO\Domain;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Mapper\DomainMapper;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Waterfront\Infra\PasswordGenerator\DefaultGenerator;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ManagerDomainService::class)]
class ManagerDomainServiceTest extends IntegrationTestCase
{
    private Environment $environment;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->environment = new CloudstackEnvironmentFactory()->createOne([
            'domain_id' => Str::uuid()->toString(),
        ]);

        $this->customer = new CustomerFactory()->createOne();
    }

    /**
     * @throws AdminClientFactoryException
     * @throws Exception
     * @throws ClientException
     */
    #[Test]
    public function createManagerDomainSubscription(): void
    {
        $baseClient = $this->createMock(CloudStackBaseClient::class);

        $baseClient
            ->expects(self::once())
            ->method('execute')
            ->with('listDomainChildren', [
                'page' => 1,
                'pagesize' => 500,
                'id' => $this->environment->domain_id,
                'listall' => 'true',
                'name' => 'cs12345678',
            ])
            ->willReturn([
                'count' => 0,
                'listDomainChildren' => [],
            ]);

        $clientMock = $this->createMock(CloudStackClient::class);

        $clientMock
            ->expects(self::exactly(2))
            ->method('listDomainChildren')
            ->willReturn(
                new CloudStackPaginationIterator(
                    client: $baseClient,
                    command: 'listDomainChildren',
                    parameters: [
                        'id' => $this->environment->domain_id,
                        'listall' => 'true',
                        'name' => 'cs12345678',
                    ],
                    type: 'listDomainChildren',
                    mapper: new DomainMapper()
                )
            );

        $clientMock->expects(self::once())
            ->method('createAccount')
            ->willReturn(new Account('123', '123', '123'));

        $domainId = '1234321';

        $clientMock->expects(self::once())
            ->method('createDomain')
            ->willReturn(new Domain($domainId, '123', '123'));

        $adminClientFactory = $this->createStub(AdminClientFactoryInterface::class);

        $adminClientFactory
            ->method('create')
            ->willReturn($clientMock);

        $this->instance(AdminClientFactoryInterface::class, $adminClientFactory);

        $managerDomainService = self::resolve(ManagerDomainService::class);

        $managerDomainDeployment = $managerDomainService->create(
            environment: $this->environment,
            customer: $this->customer,
        );

        self::assertSame($this->customer->id, $managerDomainDeployment->customer_id);
        self::assertSame($this->environment->id, $managerDomainDeployment->environment_id);

        self::assertSame($domainId, $managerDomainDeployment->domain_id);

        Queue::assertNotPushed(SendEmail::class);
    }

    #[Test]
    public function deletedManagerDomainSubscriptionOnCloudstackException(): void
    {
        $baseClient = $this->createMock(CloudStackBaseClient::class);

        $baseClient
            ->expects(self::once())
            ->method('execute')
            ->with('listDomainChildren', [
                'page' => 1,
                'pagesize' => 500,
                'id' => $this->environment->domain_id,
                'listall' => 'true',
                'name' => 'cs12345678',
            ])
            ->willReturn([
                'count' => 0,
                'listDomainChildren' => [],
            ]);

        $clientMock = $this->createMock(CloudStackClient::class);

        $clientMock
            ->expects(self::exactly(2))
            ->method('listDomainChildren')
            ->willReturn(
                new CloudStackPaginationIterator(
                    client: $baseClient,
                    command: 'listDomainChildren',
                    parameters: [
                        'id' => $this->environment->domain_id,
                        'listall' => 'true',
                        'name' => 'cs12345678',
                    ],
                    type: 'listDomainChildren',
                    mapper: new DomainMapper()
                )
            );

        $clientMock->expects(self::once())
            ->method('createAccount')
            ->willThrowException(new ClientException(
                'Cloudstack mock error'
            ));

        $domainId = '1234321';

        $clientMock->expects(self::once())
            ->method('createDomain')
            ->willReturn(new Domain($domainId, '123', '123'));

        $adminClientFactory = $this->createStub(AdminClientFactoryInterface::class);

        $adminClientFactory
            ->method('create')
            ->willReturn($clientMock);

        $this->instance(AdminClientFactoryInterface::class, $adminClientFactory);

        $managerDomainProduct = new ProductFactory()->vps()->createOne();

        $managerDomainService = self::resolve(ManagerDomainService::class);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($managerDomainProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::REGISTRATION->value,
            ]);

        self::expectException(ClientException::class);
        self::expectExceptionMessageIs('Cloudstack mock error');

        $managerDomainService->create(
            environment: $this->environment,
            customer: $this->customer,
        );
    }

    #[Test]
    public function customerDataInManagerDomain(): void
    {
        $domainId = '1234321';

        $baseClient = $this->createMock(CloudStackBaseClient::class);

        $baseClient
            ->expects(self::once())
            ->method('execute')
            ->with('listDomainChildren', [
                'page' => 1,
                'pagesize' => 500,
                'id' => $this->environment->domain_id,
                'listall' => 'true',
                'name' => 'cs12345678',
            ])
            ->willReturn([
                'count' => 0,
                'listDomainChildren' => [],
            ]);

        $clientMock = $this->createMock(CloudStackClient::class);

        $clientMock
            ->expects(self::exactly(2))
            ->method('listDomainChildren')
            ->willReturn(
                new CloudStackPaginationIterator(
                    client: $baseClient,
                    command: 'listDomainChildren',
                    parameters: [
                        'id' => $this->environment->domain_id,
                        'listall' => 'true',
                        'name' => 'cs12345678',
                    ],
                    type: 'listDomainChildren',
                    mapper: new DomainMapper()
                )
            );

        $clientMock->expects(self::once())
            ->method('createAccount')
            ->with(
                $domainId,
                self::anything(), // Generated username.
                $this->customer->first_name,
                $this->customer->last_name,
                $this->customer->email,
                self::anything(), // Generated password
                $this->environment->default_role_id
            )
            ->willReturn(new Account('123', '123', '123'));

        $clientMock->expects(self::once())
            ->method('createDomain')
            ->willReturn(new Domain($domainId, '123', '123'));

        $adminClientFactory = $this->createStub(AdminClientFactoryInterface::class);

        $adminClientFactory
            ->method('create')
            ->willReturn($clientMock);

        $this->instance(AdminClientFactoryInterface::class, $adminClientFactory);

        $managerDomainProduct = new ProductFactory()->vps()->createOne();

        $managerDomainService = self::resolve(ManagerDomainService::class);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($managerDomainProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::REGISTRATION->value,
            ]);

        $managerDomainDeployment = $managerDomainService->create(
            environment: $this->environment,
            customer: $this->customer,
        );

        self::assertSame($domainId, $managerDomainDeployment->domain_id);

        Queue::assertNotPushed(SendEmail::class);
    }

    #[Test]
    public function deleteDomain(): void
    {
        $jobId = Uuid::uuid4();
        $dispatcherMock = self::createMock(Dispatcher::class);
        $mockLogger = self::createMock(LoggerInterface::class);

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($this->environment)
            ->createOne();

        self::assertNotNull($managerDomainDeployment->domain_id);

        $clientMock = self::createMock(CloudStackClient::class);
        $clientMock->expects(self::once())
            ->method('deleteDomain')
            ->with($managerDomainDeployment->domain_id, true)
            ->willReturn(new AsynchronousCloudstackResponse(jobId: (string) $jobId));

        $clientMock->expects(self::once())
            ->method('listAccounts')
            ->with($managerDomainDeployment->domain_id, $managerDomainDeployment->account)
            ->willReturn(new ArrayIterator([new Account('123', $managerDomainDeployment->domain_name, $managerDomainDeployment->domain_id)]));

        $clientAdminFactoryMock = self::createMock(AdminClientFactoryInterface::class);
        $clientAdminFactoryMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($clientMock);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())
            ->method('create')
            ->with($managerDomainDeployment)
            ->willReturn($clientMock);

        $managerDomainService = new ManagerDomainService(
            $clientAdminFactoryMock,
            $clientFactoryMock,
            self::resolve(DefaultGenerator::class),
            CloudstackSerializerFactory::get(),
            $dispatcherMock,
            $mockLogger
        );

        $mockLogger->expects(self::once())
            ->method('info')
            ->with(sprintf(
                'Cloudstack deleting domain [%s] with job ID [%s]',
                $managerDomainDeployment->domain_id,
                $jobId,
            ));

        $dispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn ($job) => $job instanceof DeleteDomainJob));

        $serviceDeleteDomain = $managerDomainService->deleteDomain($managerDomainDeployment);

        self::assertTrue($serviceDeleteDomain);

        self::assertDatabaseHas('cloudstack_jobs', [
            'job_id' => $jobId,
        ]);
    }

    #[Test]
    public function deleteDomainTrueIfAlreadyRemoved(): void
    {
        $jobId = Uuid::uuid4();
        $dispatcherMock = self::createMock(Dispatcher::class);
        $mockLogger = self::createStub(LoggerInterface::class);

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($this->environment)
            ->createOne();

        $clientMock = self::createMock(CloudStackClient::class);
        $clientMock->expects(self::once())
            ->method('listAccounts')
            ->with($managerDomainDeployment->domain_id, $managerDomainDeployment->account)
            ->willReturn(new ArrayIterator([]));

        $clientAdminFactoryMock = self::createMock(AdminClientFactoryInterface::class);
        $clientAdminFactoryMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($clientMock);

        $managerDomainService = new ManagerDomainService(
            $clientAdminFactoryMock,
            self::resolve(ClientFactoryInterface::class),
            self::resolve(DefaultGenerator::class),
            CloudstackSerializerFactory::get(),
            $dispatcherMock,
            $mockLogger
        );

        $dispatcherMock->expects(self::never())
            ->method('dispatch')
            ->with(self::callback(fn ($job) => $job instanceof DeleteDomainJob));

        $serviceDeleteDomain = $managerDomainService->deleteDomain($managerDomainDeployment);

        self::assertTrue($serviceDeleteDomain);

        self::assertDatabaseMissing('cloudstack_jobs', [
            'job_id' => $jobId,
        ]);
    }

    #[Test]
    public function deleteDomainClientException(): void
    {
        $jobId = Uuid::uuid4();
        $dispatcherMock = self::createStub(Dispatcher::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        $exception = new ClientException('client error');

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($this->environment)
            ->createOne();

        self::assertNotNull($managerDomainDeployment->domain_id);

        $clientMock = self::createMock(CloudStackClient::class);
        $clientMock->expects(self::once())
            ->method('deleteDomain')
            ->with($managerDomainDeployment->domain_id, true)
            ->willThrowException($exception);

        $clientMock->expects(self::once())
            ->method('listAccounts')
            ->with($managerDomainDeployment->domain_id, $managerDomainDeployment->account)
            ->willReturn(new ArrayIterator([new Account('123', $managerDomainDeployment->domain_name, $managerDomainDeployment->domain_id)]));

        $clientAdminFactoryMock = self::createMock(AdminClientFactoryInterface::class);
        $clientAdminFactoryMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($clientMock);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())
            ->method('create')
            ->with($managerDomainDeployment)
            ->willReturn($clientMock);

        $managerDomainService = new ManagerDomainService(
            $clientAdminFactoryMock,
            $clientFactoryMock,
            self::resolve(DefaultGenerator::class),
            CloudstackSerializerFactory::get(),
            $dispatcherMock,
            $mockLogger
        );

        $mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'Something went wrong while trying to delete domain at Cloudstack',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

        $serviceDeleteDomain = $managerDomainService->deleteDomain($managerDomainDeployment);

        self::assertFalse($serviceDeleteDomain);

        self::assertDatabaseMissing('cloudstack_jobs', [
            'job_id' => $jobId,
        ]);
    }
}
