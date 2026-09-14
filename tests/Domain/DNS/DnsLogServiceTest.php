<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DTO\DnsRecordChangeDTO;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Enums\DnsAgentType;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Repository\DnsRecordChangeRepository;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Support\Helpers\SystemHelper;

#[CoversClass(DnsLogService::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsLogServiceTest extends IntegrationTestCase
{
    public const string LOCAL_IP_ADDRESS = '127.0.0.1';

    public const string USER_IP_ADDRESS = '192.168.1.1';

    public const string DOMAIN = 'sandwave.io';

    private Subscription $premiumDnsSubscription;

    private DnsRecordChangeDTO $systemDnsRecordChangeDTO;

    private DnsRecordChangeDTO $customerDnsRecordChangeDTO;

    private DnsLogService $dnsLogService;

    private Customer $customer;

    private SystemHelper&MockObject $systemHelper;

    private AuthenticationManager&MockObject $authenticationManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->premiumDnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->premiumDns())
            ->forDomain(self::DOMAIN)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->systemDnsRecordChangeDTO = new DnsRecordChangeDTO(
            record_type: DnsRecordType::A,
            change_type: DnsChangeType::CREATED,
            name: 'test',
            content: 'test',
            ttl: 100,
        );

        $this->customerDnsRecordChangeDTO = new DnsRecordChangeDTO(
            record_type: DnsRecordType::A,
            change_type: DnsChangeType::CREATED,
            name: 'test',
            content: 'test',
            ttl: 100,
        );

        $dnsRecordChangeRepository = self::resolve(DnsRecordChangeRepository::class);
        $subscriptionRepository = self::resolve(SubscriptionRepository::class);

        $authenticatedCustomer = new AuthenticatedCustomer(
            customer: $this->customer,
            identitySchema: new KratosIdentity(
                $this->customer->uuid,
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                new MetadataPublic(['waterfront'], [123], [], null, null, null, null),
                null,
                null,
            ),
            verified: true,
        );

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->authenticationManager->method('getAuthenticatedSubject')->willReturn($authenticatedCustomer);

        $this->systemHelper = self::createMock(SystemHelper::class);
        $this->systemHelper->method('getClientIp')->willReturn(self::USER_IP_ADDRESS);

        $this->dnsLogService = new DnsLogService(
            $dnsRecordChangeRepository,
            $subscriptionRepository,
            $this->systemHelper,
            $this->authenticationManager,
        );
    }

    #[Test]
    public function logSystemDnsRecordChange(): void
    {
        self::assertDatabaseEmpty('dns_record_changes');

        $this->systemHelper->expects(self::once())->method('isRunningInConsole')->willReturn(true);
        $this->systemHelper->method('getClientIp')->willReturn(self::LOCAL_IP_ADDRESS);

        $this->dnsLogService->log($this->systemDnsRecordChangeDTO, self::DOMAIN);

        self::assertDatabaseHas('dns_record_changes', [
            'record_type' => $this->systemDnsRecordChangeDTO->record_type,
            'change_type' => $this->systemDnsRecordChangeDTO->change_type,
            'agent_type' => DnsAgentType::SYSTEM,
            'name' => $this->systemDnsRecordChangeDTO->name,
            'content' => $this->systemDnsRecordChangeDTO->content,
            'ttl' => $this->systemDnsRecordChangeDTO->ttl,
            'priority' => null,
            'weight' => null,
            'port' => null,
            'changed_by_uuid' => null,
            'subscription_id' => $this->premiumDnsSubscription->id,
            'ip_address' => self::LOCAL_IP_ADDRESS,
        ]);
    }

    #[Test]
    public function logUserDnsRecordChange(): void
    {
        self::assertDatabaseEmpty('dns_record_changes');

        $this->dnsLogService->log($this->systemDnsRecordChangeDTO, self::DOMAIN);

        self::assertDatabaseHas('dns_record_changes', [
            'record_type' => $this->customerDnsRecordChangeDTO->record_type,
            'change_type' => $this->customerDnsRecordChangeDTO->change_type,
            'agent_type' => DnsAgentType::CUSTOMER,
            'name' => $this->customerDnsRecordChangeDTO->name,
            'content' => $this->customerDnsRecordChangeDTO->content,
            'ttl' => $this->customerDnsRecordChangeDTO->ttl,
            'priority' => null,
            'weight' => null,
            'port' => null,
            'changed_by_uuid' => $this->customer->uuid,
            'subscription_id' => $this->premiumDnsSubscription->id,
            'ip_address' => self::USER_IP_ADDRESS,
        ]);
    }

    #[Test]
    public function logUserDnsRecordChangeWithTrailingDot(): void
    {
        $domainWithTrailingDot = sprintf('%s.', self::DOMAIN);

        self::assertDatabaseEmpty('dns_record_changes');

        $this->dnsLogService->log($this->systemDnsRecordChangeDTO, $domainWithTrailingDot);

        self::assertDatabaseHas('dns_record_changes', [
            'record_type' => $this->customerDnsRecordChangeDTO->record_type,
            'change_type' => $this->customerDnsRecordChangeDTO->change_type,
            'agent_type' => DnsAgentType::CUSTOMER,
            'name' => $this->customerDnsRecordChangeDTO->name,
            'content' => $this->customerDnsRecordChangeDTO->content,
            'ttl' => $this->customerDnsRecordChangeDTO->ttl,
            'priority' => null,
            'weight' => null,
            'port' => null,
            'changed_by_uuid' => $this->customer->uuid,
            'subscription_id' => $this->premiumDnsSubscription->id,
            'ip_address' => self::USER_IP_ADDRESS,
        ]);
    }

    #[Test]
    public function logSingleMxRecord(): void
    {
        $testName = 'test-name';
        $testContent = 'test-content';
        $testPriority = 1337;
        $testTtl = 3600;
        $expectedAgentType = DnsAgentType::CUSTOMER;

        $expectedDto = new DnsRecordChangeDTO(
            record_type: DnsRecordType::MX,
            change_type: DnsChangeType::CREATED,
            name: $testName,
            content: $testContent,
            // Default TTL if it cannot be found
            // https://doc.powerdns.com/authoritative/settings.html#default-ttl
            ttl: $testTtl,
            priority: $testPriority,
        );

        $mockDnsRecordChangeRepository = self::createMock(DnsRecordChangeRepository::class);
        $mockSubscriptionRepository = self::createMock(SubscriptionRepository::class);

        $mxRecord = new MxRecord($testName, $testContent, $testPriority, $testTtl);

        $mockSubscriptionRepository
            ->expects(self::once())
            ->method('getActiveDnsSubscription')
            ->with(self::DOMAIN)
            ->willReturn($this->premiumDnsSubscription);

        $mockDnsRecordChangeRepository
            ->expects(self::once())
            ->method('createDnsRecordChange')
            ->with(
                $expectedDto,
                $expectedAgentType,
                $this->premiumDnsSubscription,
                self::USER_IP_ADDRESS,
            );

        $dnsLogService = new DnsLogService(
            $mockDnsRecordChangeRepository,
            $mockSubscriptionRepository,
            $this->systemHelper,
            $this->authenticationManager,
        );

        $dnsLogService->logSingleRecordOfDnsZone(
            $mxRecord,
            self::DOMAIN,
            DnsChangeType::CREATED,
        );
    }

    #[Test]
    public function logSingleSrvRecord(): void
    {
        $testName = 'test-name';
        $testContent = 'test-content';
        $testPriority = 1337;
        $testWeight = 1338;
        $testPort = 1339;
        $testTtl = 3600;
        $expectedAgentType = DnsAgentType::CUSTOMER;

        $expectedDto = new DnsRecordChangeDTO(
            record_type: DnsRecordType::SRV,
            change_type: DnsChangeType::CREATED,
            name: $testName,
            content: $testContent,
            // Default TTL if it cannot be found
            // https://doc.powerdns.com/authoritative/settings.html#default-ttl
            ttl: $testTtl,
            priority: $testPriority,
            weight: $testWeight,
            port: $testPort,
        );

        $mockDnsRecordChangeRepository = self::createMock(DnsRecordChangeRepository::class);
        $mockSubscriptionRepository = self::createMock(SubscriptionRepository::class);

        $mxRecord = new SrvRecord($testName, $testContent, $testPriority, $testWeight, $testPort, $testTtl);

        $mockSubscriptionRepository
            ->expects(self::once())
            ->method('getActiveDnsSubscription')
            ->with(self::DOMAIN)
            ->willReturn($this->premiumDnsSubscription);

        $mockDnsRecordChangeRepository
            ->expects(self::once())
            ->method('createDnsRecordChange')
            ->with(
                $expectedDto,
                $expectedAgentType,
                $this->premiumDnsSubscription,
                self::USER_IP_ADDRESS,
            );

        $dnsLogService = new DnsLogService(
            $mockDnsRecordChangeRepository,
            $mockSubscriptionRepository,
            $this->systemHelper,
            $this->authenticationManager,
        );

        $dnsLogService->logSingleRecordOfDnsZone(
            $mxRecord,
            self::DOMAIN,
            DnsChangeType::CREATED,
        );
    }

    #[Test]
    public function invalidGroup(): void
    {
        $extensionSubscription = new SubscriptionFactory()
            ->for($this->customer->firstOrFail())
            ->for(new ProductFactory()->nlDomain())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        self::expectException(SubscriptionNotFoundException::class);
        self::expectExceptionMessageIs(
            sprintf(
                'Cannot find subscription with domain %s for DNS log',
                $extensionSubscription->domain,
            ),
        );

        self::assertNotNull($extensionSubscription->domain);

        $this->dnsLogService->log($this->customerDnsRecordChangeDTO, $extensionSubscription->domain);

        self::assertDatabaseEmpty('dns_record_changes');
    }

    #[Test]
    public function logDnsWithCancelledSubscription(): void
    {
        $testName = 'test-name';
        $testContent = '13.37.13.37';
        $testTtl = 3600;
        $expectedAgentType = DnsAgentType::CUSTOMER;

        $this->premiumDnsSubscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->premiumDnsSubscription->save();

        $expectedDto = new DnsRecordChangeDTO(
            record_type: DnsRecordType::A,
            change_type: DnsChangeType::CREATED,
            name: $testName,
            content: $testContent,
            // Default TTL if it cannot be found
            // https://doc.powerdns.com/authoritative/settings.html#default-ttl
            ttl: $testTtl,
        );

        $mockDnsRecordChangeRepository = self::createMock(DnsRecordChangeRepository::class);

        $aRecord = new ARecord($testName, $testContent, $testTtl);

        $mockDnsRecordChangeRepository
            ->expects(self::once())
            ->method('createDnsRecordChange')
            ->with(
                $expectedDto,
                $expectedAgentType,
                self::callback(
                    fn (Subscription $subscription) => $subscription->id === $this->premiumDnsSubscription->id,
                ),
                self::USER_IP_ADDRESS,
            );

        $dnsLogService = new DnsLogService(
            $mockDnsRecordChangeRepository,
            $this->app->make(SubscriptionRepository::class),
            $this->systemHelper,
            $this->authenticationManager,
        );

        $dnsLogService->logSingleRecordOfDnsZone(
            $aRecord,
            self::DOMAIN,
            DnsChangeType::CREATED,
        );
    }

    #[Test]
    public function logMultipleRecords(): void
    {
        $testAName = 'a';
        $testAContent = self::LOCAL_IP_ADDRESS;
        $testATtl = 10;

        $testCnameName = 'c';
        $testCnameContent = self::LOCAL_IP_ADDRESS;
        $testCnameTtl = 10;

        $expectedChangeType = DnsChangeType::CREATED;

        $records = [
            new ARecord($testAName, $testAContent, $testATtl),
            new CnameRecord($testCnameName, $testCnameContent, $testCnameTtl),
        ];

        $expectedCalls = count($records);

        $expectedAgentType = DnsAgentType::CUSTOMER;

        $expectedADto = new DnsRecordChangeDTO(
            record_type: DnsRecordType::A,
            change_type: $expectedChangeType,
            name: $testAName,
            content: $testAContent,
            // Default TTL if it cannot be found
            // https://doc.powerdns.com/authoritative/settings.html#default-ttl
            ttl: $testATtl,
        );

        $expectedCnameDto = new DnsRecordChangeDTO(
            record_type: DnsRecordType::CNAME,
            change_type: $expectedChangeType,
            name: $testCnameName,
            content: $testCnameContent,
            // Default TTL if it cannot be found
            // https://doc.powerdns.com/authoritative/settings.html#default-ttl
            ttl: $testCnameTtl,
        );

        $mockDnsRecordChangeRepository = self::mock(DnsRecordChangeRepository::class);
        $mockSubscriptionRepository = self::createMock(SubscriptionRepository::class);

        $mockSubscriptionRepository
            ->expects(self::exactly($expectedCalls))
            ->method('getActiveDnsSubscription')
            ->with(self::DOMAIN)
            ->willReturn($this->premiumDnsSubscription);

        $mockDnsRecordChangeRepository
            ->shouldReceive('createDnsRecordChange')
            ->once()
            ->withArgs(
                fn (
                    DnsRecordChangeDTO $dnsRecordChangeDTO,
                    DnsAgentType $dnsAgentType,
                    Subscription $subscription,
                    string $ip_address,
                ) => (
                    $dnsRecordChangeDTO->record_type === $expectedADto->record_type
                    && $dnsRecordChangeDTO->content === $expectedADto->content
                    && $dnsAgentType === $expectedAgentType
                    && $subscription->id === $this->premiumDnsSubscription->id
                    && $ip_address === self::USER_IP_ADDRESS
                ),
            );

        $mockDnsRecordChangeRepository
            ->shouldReceive('createDnsRecordChange')
            ->once()
            ->withArgs(
                fn (
                    DnsRecordChangeDTO $dnsRecordChangeDTO,
                    DnsAgentType $dnsAgentType,
                    Subscription $subscription,
                    string $ip_address,
                ) => (
                    $dnsRecordChangeDTO->record_type === $expectedCnameDto->record_type
                    && $dnsRecordChangeDTO->content === $expectedCnameDto->content
                    && $dnsAgentType === $expectedAgentType
                    && $subscription->id === $this->premiumDnsSubscription->id
                    && $ip_address === self::USER_IP_ADDRESS
                ),
            );

        $dnsLogService = new DnsLogService(
            $mockDnsRecordChangeRepository,
            $mockSubscriptionRepository,
            $this->systemHelper,
            $this->authenticationManager,
        );

        $dnsLogService->logMultipleRecords(
            $records,
            self::DOMAIN,
            $expectedChangeType,
        );
    }
}
