<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Job;

use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Job\SetNameserversForDomainJob;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(SetNameserversForDomainJob::class)]
class SetNameserversForDomainJobTest extends IntegrationTestCase
{
    private const string DOMAIN = 'example.nl';

    private DnsDeploymentRepository&MockInterface $mockDnsDeploymentRepository;

    private DnsDeployment $dnsDeployment;

    private DomainDeployment $domainDeployment;

    private Subscription $extensionSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDnsDeploymentRepository = self::mock(DnsDeploymentRepository::class);

        $extensionProduct = ProductFactory::new()->for(ProductGroupFactory::new()->extension())->createOne();

        $this->extensionSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->forDomain(self::DOMAIN)
            ->for($extensionProduct)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $dnsProduct = ProductFactory::new()->freeDns()->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->for($this->extensionSubscription->customer)
            ->forDomain(self::DOMAIN)
            ->for($dnsProduct)
            ->administrativeStatusActive()
            ->parentSubscription($this->extensionSubscription)
            ->createOne();

        $this->dnsDeployment = DnsDeploymentFactory::new()
            ->withInternalNameserver()
            ->for($dnsSubscription)
            ->createOne();

        $this->domainDeployment = DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->extensionSubscription->uuid,
        ]);
    }

    #[Test]
    public function handleDispatchesUpdateDomainNameRegistrationJob(): void
    {
        Bus::fake();

        $nameservers = [
            new Nameserver('ns1.example.nl'),
            new Nameserver('ns2.example.nl'),
        ];

        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDnsDeploymentFromDomain')
            ->once()
            ->with(self::DOMAIN)
            ->andReturn($this->dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDomainDeployment')
            ->once()
            ->with($this->dnsDeployment)
            ->andReturn($this->domainDeployment);

        $this->mockDnsDeploymentRepository
            ->shouldReceive('getNameservers')
            ->once()
            ->with($this->dnsDeployment)
            ->andReturn($nameservers);

        $job = new SetNameserversForDomainJob(self::DOMAIN);
        $job->handle(
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            busDispatcher: self::resolve(Dispatcher::class),
        );

        Bus::assertDispatched(UpdateDomainNameRegistrationJob::class);
    }

    #[Test]
    public function handleThrowsWhenDnsDeploymentIsNull(): void
    {
        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDnsDeploymentFromDomain')
            ->once()
            ->with(self::DOMAIN)
            ->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(sprintf('Expected to have DnsDeployment for domain %s', self::DOMAIN));

        $job = new SetNameserversForDomainJob(self::DOMAIN);
        $job->handle(
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            busDispatcher: self::resolve(Dispatcher::class),
        );
    }

    #[Test]
    public function handleThrowsWhenDomainDeploymentIsNull(): void
    {
        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDnsDeploymentFromDomain')
            ->once()
            ->with(self::DOMAIN)
            ->andReturn($this->dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDomainDeployment')
            ->once()
            ->with($this->dnsDeployment)
            ->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Expected to have DomainDeployment with DNS for domain %s',
            self::DOMAIN,
        ));

        $job = new SetNameserversForDomainJob(self::DOMAIN);
        $job->handle(
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            busDispatcher: self::resolve(Dispatcher::class),
        );
    }

    #[Test]
    public function handleThrowsWhenNameserversFetchFails(): void
    {
        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDnsDeploymentFromDomain')
            ->once()
            ->with(self::DOMAIN)
            ->andReturn($this->dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->shouldReceive('getDomainDeployment')
            ->once()
            ->with($this->dnsDeployment)
            ->andReturn($this->domainDeployment);

        $this->mockDnsDeploymentRepository
            ->shouldReceive('getNameservers')
            ->once()
            ->with($this->dnsDeployment)
            ->andThrow(new FailedToFetchNameserversException(self::DOMAIN));

        $this->expectException(FailedToFetchNameserversException::class);

        $job = new SetNameserversForDomainJob(self::DOMAIN);
        $job->handle(
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            busDispatcher: self::resolve(Dispatcher::class),
        );
    }

    #[Test]
    public function failedSetsTechnicalStatusToFailedOnSubscription(): void
    {
        $testException = new Exception('Something went wrong');

        $job = new SetNameserversForDomainJob(self::DOMAIN);
        $job->failed($testException);

        $this->extensionSubscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::FAILED->value, $this->extensionSubscription->technical_status);
        self::assertNotNull($this->domainDeployment->last_result_received);
        self::assertNotNull($this->domainDeployment->last_result);

        /** @var array{message: string, exception: string} $lastResult */
        $lastResult = json_decode($this->domainDeployment->last_result, true);
        self::assertSame('Failed to start nameserver update for domain', $lastResult['message']);
        self::assertSame('Something went wrong', $lastResult['exception']);
    }

    #[Test]
    public function failedLogsErrorWhenNoDomainDeploymentFound(): void
    {
        $testException = new Exception('Something went wrong');

        $mockLogger = self::mock(LoggerInterface::class);
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $mockLogger
            ->shouldReceive('error')
            ->once()
            ->with(
                'Trying to set domain subscription to FAILED but unable to find active domain subscription for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'nonexistent-domain.nl',
                    LoggingContextKeys::EXCEPTION => $testException,
                ],
            );

        $job = new SetNameserversForDomainJob('nonexistent-domain.nl');
        $job->failed($testException);
    }
}
