<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Domain;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\DomainNameController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\DNS\Listeners\DnsCreationListener;
use Waterfront\Domain\Domains\Actions\DomainNameCoupleAction;
use Waterfront\Domain\Domains\Actions\DomainNameDecoupleAction;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Listeners\DomainCreationListener;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameCoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameDecoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Rules\DomainNameCoupleAllowedRule;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainNameController::class)]
#[AllowMockObjectsWithoutExpectations]
class DomainNameControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private DomainService&MockObject $domainService;

    private DomainNameCoupleAction&MockObject $domainNameCoupleAction;

    private DomainNameDecoupleAction&MockObject $domainNameDecoupleAction;

    private ProductSpec $productSpec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $domainProduct = new ProductFactory()->nlDomain()->createOne();
        $this->productSpec = new ProductSpecFactory()->for($domainProduct)->createOne([
            'name' => ProductSpecName::DOMAIN_DNSSEC_ENABLED,
            'value' => '1',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->for($domainProduct)
            ->for($this->customer)
            ->createOne();

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $this->subscription->uuid,
            ]);

        $this->domainService = self::createMock(DomainService::class);

        $this->domainNameCoupleAction = self::createMock(DomainNameCoupleAction::class);

        $this->domainNameDecoupleAction = self::createMock(DomainNameDecoupleAction::class);

        $this->app->bind(DomainService::class, fn () => $this->domainService);
        $this->app->bind(DomainNameCoupleAction::class, fn () => $this->domainNameCoupleAction);
        $this->app->bind(DomainNameDecoupleAction::class, fn () => $this->domainNameDecoupleAction);
    }

    #[Test]
    public function indexDnsSec(): void
    {
        $this->domainService
            ->expects(self::once())
            ->method('retrieveDnssecKeys')
            ->with($this->subscription->domain)
            ->willReturn(['keys' => ['key1' => 1]]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.domain-name.dnssec', $this->subscription->domain),
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    'keys' => [],
                ],
            ]);
    }

    #[Test]
    public function enableDnsSecSuccess(): void
    {
        $requestData = [
            'flags' => 257,
            'alg' => 13,
            'pubKey' => 'aliEt75mjEpujeIbjTpGOKerJpOXUMKEmot8V26L4vT6eZqNbW2fAqr9ejkPdbmwLYcCoUl0AtCipuzc1ES6XQ==',
        ];

        $this->domainService
            ->expects(self::once())
            ->method('enableDnssec')
            ->with($this->subscription->domain)
            ->willReturn(true);

        $this->domainService
            ->expects(self::once())
            ->method('retrieveDnssecKeys')
            ->with($this->subscription->domain)
            ->willReturn(['keys' => ['key1' => 1]]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.enable-dnssec', $this->subscription->domain),
                $requestData,
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    'keys' => [
                        'key1' => 1,
                    ],
                ],
            ]);
    }

    #[Test]
    public function enableDnsSecWithProductSpecVariation(): void
    {
        $requestData = [
            'flags' => 257,
            'alg' => 13,
            'pubKey' => 'aliEt75mjEpujeIbjTpGOKerJpOXUMKEmot8V26L4vT6eZqNbW2fAqr9ejkPdbmwLYcCoUl0AtCipuzc1ES6XQ==',
        ];

        $this->productSpec->value = 'yes';
        $this->productSpec->save();
        $this->productSpec->refresh();

        $this->domainService
            ->expects(self::once())
            ->method('enableDnssec')
            ->with($this->subscription->domain)
            ->willReturn(true);

        $this->domainService
            ->expects(self::once())
            ->method('retrieveDnssecKeys')
            ->with($this->subscription->domain)
            ->willReturn(['keys' => ['key1' => 1]]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.enable-dnssec', $this->subscription->domain),
                $requestData,
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    'keys' => [
                        'key1' => 1,
                    ],
                ],
            ]);
    }

    #[Test]
    public function enableDnsSecSuccessWithOutPostData(): void
    {
        $this->domainService
            ->expects(self::once())
            ->method('enableDnssec')
            ->with($this->subscription->domain)
            ->willReturn(true);

        $this->domainService
            ->expects(self::once())
            ->method('retrieveDnssecKeys')
            ->with($this->subscription->domain)
            ->willReturn(['keys' => ['key1' => 1]]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.enable-dnssec', $this->subscription->domain),
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    'keys' => [
                        'key1' => 1,
                    ],
                ],
            ]);
    }

    #[Test]
    public function enableDnsSecFailedNotEnabled(): void
    {
        $requestData = [
            'flags' => 257,
            'alg' => 13,
            'pubKey' => 'aliEt75mjEpujeIbjTpGOKerJpOXUMKEmot8V26L4vT6eZqNbW2fAqr9ejkPdbmwLYcCoUl0AtCipuzc1ES6XQ==',
        ];

        $this->domainService
            ->expects(self::once())
            ->method('enableDnssec')
            ->with($this->subscription->domain)
            ->willReturn(false);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.enable-dnssec', $this->subscription->domain),
                $requestData,
            )
            ->assertServerError()
            ->assertJson([
                'message' => 'Could not enable DNSSEC',
            ]);
    }

    #[Test]
    public function enableDnsSecFailedMissingPostInput(): void
    {
        $requestData = [
            'flags' => 257,
            'alg' => 13,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.enable-dnssec', $this->subscription->domain),
                $requestData,
            )
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Dit veld is verplicht wanneer flags / alg aanwezig is.',
                'errors' => [
                    'pubKey' => ['Dit veld is verplicht wanneer flags / alg aanwezig is.'],
                ],
            ]);
    }

    #[Test]
    public function enableDnsSecFailedInvalidInputFormat(): void
    {
        $requestData = [
            'flags' => 'input',
            'alg' => 13,
            'pubKey' => '75mjEpujeIbjTpGOKerJpOXUMKEmot8V26L4vT6eZqNbW2fAqr9ejkPdbmwLYcCoUl0AtCipuzc1ES6XQ==',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.enable-dnssec', $this->subscription->domain),
                $requestData,
            )
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Dit veld dient een geheel getal te zijn.',
                'errors' => [
                    'flags' => ['Dit veld dient een geheel getal te zijn.'],
                ],
            ]);
    }

    #[Test]
    public function retryProvisionFailsWithoutDnsSubscription(): void
    {
        $transferCode = 'test-token';
        $domainContact = new DomainContactFactory()->state(['customer_id' => $this->customer->id])->createOne();
        $domain = $this->subscription->domain;

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Retry Provisioning - Failed because DNS subscription could not be found for domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                ],
            );

        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $this->subscription->technical_status = TechnicalStatus::FAILED->value;
        $this->subscription->save();

        self::assertNotNull($this->subscription->domainDeployment);
        $this->subscription->domainDeployment->contact_owner_id = $domainContact->id;
        $this->subscription->domainDeployment->save();

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.retry-provisioning', $domain),
                [
                    'contactId' => $domainContact->id,
                    'transferCode' => $transferCode,
                ],
            )
            ->assertJson([
                'message' => sprintf('Could not retrieve DNS from domain [%s]', $domain),
            ]);
    }

    #[Test]
    public function retryProvisionUpdatesTransferCode(): void
    {
        $transferCode = 'test-token';
        $oldCode = 'old-code';
        $domainContact = new DomainContactFactory()->state(['customer_id' => $this->customer->id])->createOne();
        $domain = $this->subscription->domain;
        $dnsSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->freeDns())
            ->for($this->customer)
            ->createOne([
                'parent_subscription_id' => $this->subscription->id,
            ]);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::never())->method('notice');

        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $this->subscription->technical_status = TechnicalStatus::FAILED->value;
        $this->subscription->save();

        self::assertNotNull($this->subscription->domainDeployment);
        $this->subscription->domainDeployment->contact_owner_id = $domainContact->id;
        $this->subscription->domainDeployment->transfer_secret = $oldCode;
        $this->subscription->domainDeployment->save();

        $dnsListenerMock = self::mock(DnsCreationListener::class);
        $dnsListenerMock->shouldReceive('setJob')->once();
        $dnsListenerMock
            ->shouldReceive('handle')
            ->once()
            ->withArgs(fn (CreateDns $event) => $event->subscriptionUuid === $dnsSubscription->uuid);

        $this->app->bind(DnsCreationListener::class, fn () => $dnsListenerMock);

        $domainListenerMock = self::mock(DomainCreationListener::class);
        $domainListenerMock->shouldReceive('setJob')->once();
        $domainListenerMock
            ->shouldReceive('handle')
            ->once()
            ->withArgs(fn (CreateDomain $event) => $event->subscription->uuid === $this->subscription->uuid);

        $this->app->bind(DomainCreationListener::class, fn () => $domainListenerMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.retry-provisioning', $domain),
                [
                    'contactId' => $domainContact->id,
                    'transferCode' => $transferCode,
                ],
            )
            ->assertJson([
                'message' => 'technical_subscription.provisioning.initiated_retry_success',
            ])
            ->assertOk();

        self::assertSame($transferCode, $this->subscription->domainDeployment->refresh()->transfer_secret);
    }

    #[Test]
    public function coupleDomainSuccess(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->domainNameCoupleAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription->domain, $subscription->fresh());

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.couple', $this->subscription->uuid),
                ['uuid' => $subscription->uuid],
            )
            ->assertOk()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('status.success'),
            ]);
    }

    #[Test]
    public function coupleDomainExceptionFailure(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->domainNameCoupleAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription->domain, $subscription->fresh())
            ->willThrowException(new DomainNameCoupleActionException((string) $this->subscription->domain));

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.couple', $this->subscription->uuid),
                ['uuid' => Uuid::fromString($subscription->uuid)],
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)->translate('domain-name.couple-failed'),
            ]);
    }

    #[Test]
    public function coupleDomainValidationException(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $validator = Validator::make([
            'uuid' => Uuid::uuid4(),
        ], ['uuid' => [new DomainNameCoupleAllowedRule(self::resolve(ProvisioningRequestRepository::class))]]);

        $this->domainNameCoupleAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription->domain, $subscription->fresh())
            ->willThrowException(new ValidationException($validator));

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.couple', $this->subscription->uuid),
                ['uuid' => Uuid::fromString($subscription->uuid)],
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)->translate('domain_name_couple_not_allowed'),
            ]);
    }

    #[Test]
    public function decoupleDomainSuccess(): void
    {
        $decoupleTarget = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->domainNameDecoupleAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription->domain, $decoupleTarget->fresh());

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.decouple', $this->subscription->uuid),
                ['uuid' => $decoupleTarget->uuid],
            )
            ->assertOk()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('status.success'),
            ]);
    }

    #[Test]
    public function decoupleDomainExceptionFailure(): void
    {
        $decoupleTarget = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->domainNameDecoupleAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription->domain, $decoupleTarget->fresh())
            ->willThrowException(new DomainNameDecoupleActionException((string) $this->subscription->domain));

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-name.decouple', $this->subscription->uuid),
                ['uuid' => $decoupleTarget->uuid],
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)->translate('domain-name.decouple-failed'),
            ]);
    }
}
