<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Listeners;

use Illuminate\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\DNS\Listeners\DnsProvisionedListener;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Jobs\RegisterDomainNameJob;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DnsProvisionedListener::class)]
class DnsProvisionedListenerTest extends TestCase
{
    use RefreshDatabase;

    public const string DOMAIN = 'test-domain.nl';

    private DnsDeployment $dnsDeployment;

    private LoggerInterface&MockObject $mockLogger;

    private DnsDeploymentRepository&MockObject $mockDnsDeploymentRepository;

    private DomainService&MockObject $mockDomainService;

    private Dispatcher&MockObject $mockDispatcher;

    private DomainDetailsDTO $domainDetails;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsDeployment = new DnsDeploymentFactory()->for(
            new SubscriptionFactory()->withCustomer()->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->dns())
                    ->premiumDns()
                    ->has(
                        new ProductSpecFactory()->state([
                            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
                            'value' => true,
                        ]),
                    ),
            ),
        )->makeOne();

        $this->mockLogger = self::createMock(LoggerInterface::class);
        $this->mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $this->mockDispatcher = self::createMock(Dispatcher::class);
        $this->mockDomainService = self::createMock(DomainService::class);

        $domainDetails = include __DIR__ . '/data/domainDetailsValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);
        $this->domainDetails = $domainDetails;

        $this->provider = new ProviderFactory()->domainPlaceholder()->createOne();
    }

    #[Test]
    public function alreadyRegisteredDomainShouldDispatchUpdate(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->state(['domain' => self::DOMAIN]),
            )
            ->for($this->provider)
            ->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'DNS deployment provisioned for [{subscription.uuid}]',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
                [
                    sprintf('Domain already registered, will update domain %s', self::DOMAIN),
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
            ));

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, $domainDeployment->provider->slug)
            ->willReturn($this->domainDetails);

        $this->mockDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateDomainNameRegistrationJob::class));

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function notRegisteredDomainShouldDispatchRegister(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->state(['domain' => self::DOMAIN]),
            )
            ->for($this->provider)
            ->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'DNS deployment provisioned for [{subscription.uuid}]',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
                [
                    sprintf('Domain not registered, will register with domain nameservers %s', self::DOMAIN),
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
            ));

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDomainService
            ->expects(self::once())
            ->method('registrationRequiresDnsBeforeSubmission')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, $domainDeployment->provider->slug)
            ->willThrowException(new FetchDomainException());

        $this->mockDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RegisterDomainNameJob::class));

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function pendingDomainWithDnsBeforeSubmissionRequirementShouldDispatchRegister(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->technicalStatusPending()
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->state(['domain' => self::DOMAIN]),
            )
            ->for($this->provider)
            ->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'DNS deployment provisioned for [{subscription.uuid}]',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
                [
                    sprintf('Domain not registered, will register with domain nameservers %s', self::DOMAIN),
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
            ));

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDomainService
            ->expects(self::once())
            ->method('registrationRequiresDnsBeforeSubmission')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, $domainDeployment->provider->slug)
            ->willThrowException(new FetchDomainException());

        $this->mockDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RegisterDomainNameJob::class));

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function withFailedDomainSubscriptionCheckShouldNotDispatchJob(): void
    {
        $domainDeployment = new DomainDeploymentFactory()->for(
            new SubscriptionFactory()
                ->withCustomer()
                ->technicalStatus(TechnicalStatus::FAILED->value)
                ->for(new ProductFactory()->nlDomain()),
        )->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'DNS deployment provisioned for [{subscription.uuid}]',
                        [
                            LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                        ],
                    ],
                    [
                        'Domain registration failed, not trying to register or update domain',
                        [
                            LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription->uuid,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                        ],
                    ],
                ),
            );

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDispatcher->expects(self::never())->method('dispatch');

        $this->mockDomainService->expects(self::never())->method('registrationRequiresDnsBeforeSubmission');

        $this->mockDomainService->expects(self::never())->method('fetchDomain');

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function withoutDomainDeploymentShouldNotDispatchJobs(): void
    {
        $this->mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'DNS deployment provisioned for [{subscription.uuid}]',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                ],
            );

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn(null);

        $this->mockLogger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'DnsProvisioned without domain parent subscription or product',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                ],
            );

        $this->mockDispatcher->expects(self::never())->method('dispatch');

        $this->mockDomainService->expects(self::never())->method('registrationRequiresDnsBeforeSubmission');

        $this->mockDomainService->expects(self::never())->method('fetchDomain');

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function transferDomainDeployment(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->technicalStatusPending()
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->state(['domain' => self::DOMAIN]),
            )
            ->for($this->provider)
            ->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'DNS deployment provisioned for [{subscription.uuid}]',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
                [
                    'Domain {domain.name} is currently transferring will not attempt to register domain',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                        LoggingContextKeys::META => [
                            'last_result' => $domainDeployment->last_result,
                            'last_result_received' => $domainDeployment->last_result_received,
                        ],
                    ],
                ],
            ));

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDomainService
            ->expects(self::once())
            ->method('registrationRequiresDnsBeforeSubmission')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, $domainDeployment->provider->slug)
            ->willThrowException(new FetchDomainException());

        $this->mockDispatcher->expects(self::never())->method('dispatch');

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function pendingValidationDomainDoesNotDispatchWhenDomainCannotBeFetched(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->technicalStatus(TechnicalStatus::PENDING->value)
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->state(['domain' => self::DOMAIN]),
            )
            ->for($this->provider)
            ->withRtrDomainStatus(RtrDomainStatus::PENDING_VALIDATION)
            ->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'DNS deployment provisioned for [{subscription.uuid}]',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
                [
                    'Domain TLD requires customer validation. Stopping domain registration until validation is completed.',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    ],
                ],
            ));

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, $domainDeployment->provider->slug)
            ->willThrowException(new FetchDomainException());

        $this->mockDispatcher->expects(self::never())->method('dispatch');

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }

    #[Test]
    public function pendingValidationDomainDispatchesUpdateWhenDomainCanBeFetched(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->technicalStatus(TechnicalStatus::PENDING->value)
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->state(['domain' => self::DOMAIN]),
            )
            ->for($this->provider)
            ->withRtrDomainStatus(RtrDomainStatus::PENDING_VALIDATION)
            ->makeOne();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'DNS deployment provisioned for [{subscription.uuid}]',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $this->dnsDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
                [
                    sprintf('Domain already registered, will update domain %s', self::DOMAIN),
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    ],
                ],
            ));

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDomainDeployment')
            ->with($this->dnsDeployment)
            ->willReturn($domainDeployment);

        $this->mockLogger->expects(self::never())->method('warning');

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, $domainDeployment->provider->slug)
            ->willReturn($this->domainDetails);

        $this->mockDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateDomainNameRegistrationJob::class));

        $listener = new DnsProvisionedListener(
            $this->mockDnsDeploymentRepository,
            $this->mockLogger,
            $this->mockDispatcher,
            $this->mockDomainService,
        );

        $listener->handle(new DnsProvisioned($this->dnsDeployment));
    }
}
