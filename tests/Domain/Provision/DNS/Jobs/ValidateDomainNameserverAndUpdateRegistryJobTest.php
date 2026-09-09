<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\DNS\Jobs;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use PurplePixie\PhpDns\DNSAnswer;
use PurplePixie\PhpDns\DNSQuery;
use PurplePixie\PhpDns\DNSResult;
use PurplePixie\PhpDns\DNSTypes;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Jobs\ValidateDomainNameserverAndUpdateRegistryJob;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\DnsHelper;
use Waterfront\Support\Jobs\AbstractQueueableJob;

#[CoversClass(ValidateDomainNameserverAndUpdateRegistryJob::class)]
class ValidateDomainNameserverAndUpdateRegistryJobTest extends TestCase
{
    private const string DOMAIN = 'test-domain.nl';

    private LoggerInterface&MockInterface $mockLogger;

    private DNSQuery&MockInterface $mockDnsQuery;

    private DnsHelper&MockInterface $mockDnsHelper;

    private DomainDeploymentRepository&MockInterface $mockDomainDeploymentRepository;

    /**
     * @var Nameserver[]
     */
    private array $testNameservers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = self::mock(LoggerInterface::class);
        $this->mockDnsQuery = self::mock(DNSQuery::class);
        $this->mockDnsHelper = self::mock(DnsHelper::class);
        $this->mockDomainDeploymentRepository = self::mock(DomainDeploymentRepository::class);

        $this->testNameservers = [
            new Nameserver('test1.ns.nl'),
            new Nameserver('test2.ns.nl'),
            new Nameserver('test3.ns.nl'),
        ];
    }

    #[Test]
    public function failedJobShouldSetTechnicalStatusToFailed(): void
    {
        $testExceptionMessage = 'A test exception that has occurred';
        $testThrowable = new Exception($testExceptionMessage);

        $mockSubscriptionRepository = self::mock(SubscriptionRepository::class);
        $mockSubscription = self::mock(Subscription::class);

        $this->app->bind(SubscriptionRepository::class, fn () => $mockSubscriptionRepository);
        $this->app->bind(LoggerInterface::class, fn () => $this->mockLogger);

        $this->mockLogger->shouldReceive('error')
            ->once()
            ->with(
                'Error trying to validate DNS for domain {domain.name} job definitely failed after {job.attempt} attempts',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                    LoggingContextKeys::EXCEPTION => $testThrowable,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                    LoggingContextKeys::META => [
                        'nameservers' => $this->testNameservers,
                    ],
                ]
            );

        $mockSubscriptionRepository
            ->shouldReceive('getSubscriptionByDomainAndGroup')
            ->once()
            ->andReturn($mockSubscription);

        $mockSubscription->shouldReceive('update')
            ->once()
            ->with([
                'technical_status' => TechnicalStatus::FAILED->value,
            ]);

        $job = new ValidateDomainNameserverAndUpdateRegistryJob(
            domain: self::DOMAIN,
            nameservers: $this->testNameservers,
        );

        $job->failed($testThrowable);
    }

    #[Test]
    public function success(): void
    {
        // I don't want to connect to the DB for this unit test.
        $domainSubscription = new Subscription([
            'uuid'                => Uuid::uuid4()->toString(),
            'product_uuid'        => Uuid::uuid4(),
            'customer_id'         => 1,
            'domain'              => self::DOMAIN,
            'start_date'          => CarbonImmutable::now(),
            'billing_period'      => 12,
            'contract_period'     => 12,
        ]);

        $domainDeployment = self::mock(DomainDeployment::class);
        $domainDeployment->shouldReceive('loadMissing');
        $domainDeployment
            ->shouldReceive('getAttribute')
            ->with('subscription')
            ->andReturn($domainSubscription);

        $nameservers = $this->testNameservers;

        $this->mockDnsHelper->shouldReceive('createDnsQuery')
            ->andReturn($this->mockDnsQuery);

        $this->mockDnsQuery->shouldReceive('query')
            ->times(3)
            ->with(self::DOMAIN, DNSTypes::NAME_NS)
            ->andReturn(
                $this->mockDnsAnswer(self::DOMAIN, $nameservers[0]->hostname),
                $this->mockDnsAnswer(self::DOMAIN, $nameservers[1]->hostname),
                $this->mockDnsAnswer(self::DOMAIN, $nameservers[2]->hostname)
            );

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[0]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[1]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[2]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockLogger->shouldReceive('warning')->never();

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                'All nameservers resolved for domain {domain.name}, updating registry',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockDomainDeploymentRepository
            ->shouldReceive('getActiveDeploymentByDomain')
            ->once()
            ->andReturn($domainDeployment);

        $mockDispatcher = self::mock(Dispatcher::class);

        $mockDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(fn (AbstractQueueableJob $job) => $job instanceof UpdateDomainNameRegistrationJob)
            ->once();

        $job = new ValidateDomainNameserverAndUpdateRegistryJob(self::DOMAIN, $nameservers);
        $job->handle(
            logger: $this->mockLogger,
            dnsHelper: $this->mockDnsHelper,
            busDispatcher: $mockDispatcher,
            domainDeploymentRepository: $this->mockDomainDeploymentRepository
        );
    }

    #[Test]
    public function jobFailedOnMissingDomainDeployment(): void
    {
        $nameservers = $this->testNameservers;

        $this->app->bind(LoggerInterface::class, fn () => $this->mockLogger);
        $this->app->bind(DnsHelper::class, fn () => $this->mockDnsHelper);
        $this->app->bind(DomainDeploymentRepository::class, fn () => $this->mockDomainDeploymentRepository);

        $this->mockDnsHelper->shouldReceive('createDnsQuery')
            ->andReturn($this->mockDnsQuery);

        $this->mockDnsQuery->shouldReceive('query')
            ->times(3)
            ->with(self::DOMAIN, DNSTypes::NAME_NS)
            ->andReturn(
                $this->mockDnsAnswer(self::DOMAIN, $nameservers[0]->hostname),
                $this->mockDnsAnswer(self::DOMAIN, $nameservers[1]->hostname),
                $this->mockDnsAnswer(self::DOMAIN, $nameservers[2]->hostname)
            );

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[0]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[1]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->expectJobProcessLog();

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[2]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockLogger->shouldReceive('warning')->never();

        $this->mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                'All nameservers resolved for domain {domain.name}, updating registry',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockDomainDeploymentRepository
            ->shouldReceive('getActiveDeploymentByDomain')
            ->once()
            ->andReturn(null);

        $expectedJobErrorMessage = sprintf(
            'Error when processing job (%s): Could not find domain deployment for domain %s',
            ValidateDomainNameserverAndUpdateRegistryJob::class,
            self::DOMAIN
        );

        $this->mockLogger
            ->shouldReceive('error')
            ->once()
            ->withArgs(fn ($message) => $message === $expectedJobErrorMessage);

        $this->mockLogger->shouldReceive('error')
            ->once()
            ->withSomeOfArgs(
                'Error trying to validate DNS for domain {domain.name} job definitely failed after {job.attempt} attempts'
            );

        $mockSubscriptionRepository = self::mock(SubscriptionRepository::class);
        $mockSubscription = self::mock(Subscription::class);

        $this->app->bind(SubscriptionRepository::class, fn () => $mockSubscriptionRepository);
        $mockSubscriptionRepository
            ->shouldReceive('getSubscriptionByDomainAndGroup')
            ->once()
            ->andReturn($mockSubscription);

        $mockSubscription->shouldReceive('update')
            ->once()
            ->with([
                'technical_status' => TechnicalStatus::FAILED->value,
            ]);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ValidateDomainNameserverAndUpdateRegistryJob::class, $body['displayName']);
            self::assertTrue($event->job->hasFailed());
            self::assertFalse($event->job->isReleased());
        });

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->dispatch(new ValidateDomainNameserverAndUpdateRegistryJob(self::DOMAIN, $nameservers));
    }

    #[Test]
    public function releaseWithIncorrectDnsAnswer(): void
    {
        $nameservers = $this->testNameservers;

        $this->app->bind(LoggerInterface::class, fn () => $this->mockLogger);
        $this->app->bind(DnsHelper::class, fn () => $this->mockDnsHelper);
        $this->app->bind(DomainDeploymentRepository::class, fn () => $this->mockDomainDeploymentRepository);

        $this->mockDnsHelper->shouldReceive('createDnsQuery')
            ->andReturn($this->mockDnsQuery);

        $this->mockDnsQuery->shouldReceive('query')
            ->andReturn(false);

        $this->expectJobProcessLog();

        $this->mockLogger
            ->shouldReceive('debug')
            ->once()
            ->with(
                sprintf(
                    'Could not resolve DNS for domain {domain.name} with nameserver %s',
                    $nameservers[0]->hostname
                ),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ValidateDomainNameserverAndUpdateRegistryJob::class, $body['displayName']);
            self::assertFalse($event->job->hasFailed());
            self::assertTrue($event->job->isReleased());
        });

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->dispatch(new ValidateDomainNameserverAndUpdateRegistryJob(self::DOMAIN, $nameservers));
    }

    #[Test]
    public function releaseOnMissingNameservers(): void
    {
        $differentNS = 'different.nameserver.nl';

        $nameservers = $this->testNameservers;

        $this->app->bind(LoggerInterface::class, fn () => $this->mockLogger);
        $this->app->bind(DnsHelper::class, fn () => $this->mockDnsHelper);
        $this->app->bind(DomainDeploymentRepository::class, fn () => $this->mockDomainDeploymentRepository);

        $this->mockDnsHelper
            ->shouldReceive('createDnsQuery')
            ->andReturn($this->mockDnsQuery);

        $this->mockDnsQuery
            ->shouldReceive('query')
            ->once()
            ->with(self::DOMAIN, DNSTypes::NAME_NS)
            ->andReturn(
                $this->mockDnsAnswer(self::DOMAIN, $differentNS),
            );

        $this->mockLogger
            ->shouldReceive('debug')
            ->never()
            ->with(
                sprintf('Resolved domain {domain.name} with nameserver %s', $nameservers[0]->hostname),
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockLogger
            ->shouldReceive('warning')
            ->with(
                sprintf('Resolved DNS for domain {domain.name} but nameserver %s is missing', $nameservers[0]->hostname),
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::META        => [
                        'nameservers_from_dns' => [$differentNS],
                    ],
                ]
            );

        $this->mockLogger
            ->shouldReceive('debug')
            ->never()
            ->with(
                'All nameservers resolved for domain {domain.name}, updating registry',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN]
            );

        $this->mockDomainDeploymentRepository
            ->shouldReceive('getActiveDeploymentByDomain')
            ->never();

        $this->expectJobProcessLog();

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ValidateDomainNameserverAndUpdateRegistryJob::class, $body['displayName']);
            self::assertFalse($event->job->hasFailed());
            self::assertTrue($event->job->isReleased());
        });

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->dispatch(new ValidateDomainNameserverAndUpdateRegistryJob(self::DOMAIN, $nameservers));
    }

    private function mockDnsAnswer(string $domain, string $nameserver): DNSAnswer
    {
        $dnsAnswer = new DNSAnswer();
        $dnsAnswer->addResult(
            new DNSResult(
                typename: 'NS',
                typeid: 2,
                class: '1',
                ttl: 3600,
                data: $nameserver,
                domain: $domain,
                string: sprintf('%s nameserver %s', $domain, $nameserver),
                extras: []
            )
        );
        return $dnsAnswer;
    }

    private function expectJobProcessLog(): void
    {
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with(
                sprintf(
                    'Job name: %s status: processing queue: sync',
                    ValidateDomainNameserverAndUpdateRegistryJob::class
                ),
                [
                    LoggingContextKeys::QUEUE_NAME => 'sync',
                    LoggingContextKeys::QUEUE_MESSAGE_NAME => ValidateDomainNameserverAndUpdateRegistryJob::class,
                    LoggingContextKeys::QUEUE_JOB_ID => '',
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ]
            );

        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with(
                sprintf(
                    'Job name: %s status: processed queue: sync',
                    ValidateDomainNameserverAndUpdateRegistryJob::class
                ),
                [
                    LoggingContextKeys::QUEUE_NAME => 'sync',
                    LoggingContextKeys::QUEUE_MESSAGE_NAME => ValidateDomainNameserverAndUpdateRegistryJob::class,
                    LoggingContextKeys::QUEUE_JOB_ID => '',
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ]
            );
    }
}
