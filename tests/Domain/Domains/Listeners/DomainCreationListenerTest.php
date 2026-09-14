<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Listeners;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Listeners\DomainCreationListener;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainCreationListener::class)]
class DomainCreationListenerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test-domain.nl';

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(DnsService::class, fn (): DnsService => self::createMock(DnsService::class));

        $customer = new CustomerFactory()->createOne();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();

        $product = new ProductFactory()->createOne([
            'product_group_id' => $extensionGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->id,
                'product_uuid' => $product->uuid,
                'domain' => self::DOMAIN,
                'net_price' => 70,
                'gross_price' => 1333,
            ]);

        $domainContact = new DomainContactFactory()->createOne([
            'customer_id' => $customer->id,
        ]);

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $this->subscription->uuid,
                'contact_owner_id' => $domainContact->id,
            ]);

        $dnsServiceMock = $this->mock(DnsService::class);
        $dnsServiceMock->shouldReceive('hasDnsZone')->andReturn(true);
        $dnsServiceMock->shouldReceive('isSlaveZone')->andReturn(false);
        $this->app->bind(DnsService::class, fn () => $dnsServiceMock);
    }

    #[Test]
    public function notRegisterWithZoneSpecEnabled(): void
    {
        self::assertNotNull($this->subscription->domainDeployment);

        $mockDomainService = self::createMock(DomainService::class);
        $mockLogger = self::createMock(LoggerInterface::class);

        $listener = new DomainCreationListener(
            domainService: $mockDomainService,
            mailer: self::createStub(MailerInterface::class),
            logger: $mockLogger,
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Initializing domain creation for subscription: {subscription.uuid} with domain: {domain.name}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                ],
            );

        $mockDomainService
            ->expects(self::once())
            ->method('registrationRequiresDnsBeforeSubmission')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $mockDomainService->expects(self::never())->method('minimalRegister');

        $mockLogger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PRODUCT_SLUG => $this->subscription->product->slug,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domain.registration',
                ],
            );

        $listener->handle(new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $this->subscription->domainDeployment,
        ));
    }

    #[Test]
    public function prevalidationOnlyDomainSubmitsRegistration(): void
    {
        self::assertNotNull($this->subscription->domainDeployment);

        $registrationResult = new RegistrationResult(DomainStatus::PENDING);
        $registrationResult->setReason('{"domainName":"test-domain.nl"}');

        $mockDomainService = self::createMock(DomainService::class);
        $mockDomainService
            ->expects(self::once())
            ->method('registrationRequiresDnsBeforeSubmission')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $mockDomainService->expects(self::never())->method('creationRequiresPreValidation');

        $mockDomainService
            ->expects(self::once())
            ->method('minimalRegister')
            ->with($this->subscription->domainDeployment)
            ->willReturn($registrationResult);

        $listener = new DomainCreationListener(
            domainService: $mockDomainService,
            mailer: self::createStub(MailerInterface::class),
            logger: self::createStub(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $listener->handle(new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $this->subscription->domainDeployment,
        ));

        $this->subscription->refresh();
        $this->subscription->domainDeployment?->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->technical_status);
        self::assertSame('{"domainName":"test-domain.nl"}', $this->subscription->domainDeployment?->last_result);
    }

    #[Test]
    public function transferDomainFailed(): void
    {
        $transferResult = new TransferResult(DomainStatus::FAILED->value);
        $transferResult->setReason(
            $failedReason = 'Bad Request: {"message":"Incorrect authorization code","type":"ValidationError"}',
        );

        $rtrClientMock = $this->createDomainProviderMock();

        $rtrClientMock->expects(self::once())->method('minimalTransfer')->willReturn($transferResult);

        $domainDeployment = $this->subscription->domainDeployment;

        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $domainDeployment->transfer_secret = 'transfer-code';

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::createStub(Mailer::class),
            logger: $logger = self::createMock(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                sprintf(
                    'Domain registration status: FAI. Reason: %s',
                    $failedReason,
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainDeployment->subscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                ],
            );

        try {
            $listener->handle($event);
        } finally {
            $domain = Subscription::where('domain', self::DOMAIN)->firstOrFail();

            self::assertSame(DomainStatus::FAILED->value, $domain->technical_status);
            self::assertNotNull($domainDeployment->last_result_received);
            self::assertSame(
                'Bad Request: {"message":"Incorrect authorization code","type":"ValidationError"}',
                $domainDeployment->last_result,
            );
        }
    }

    #[Test]
    public function transferDomainFailedExceptionResult(): void
    {
        $failedReason = 'Contact is missing information for the transfer of this domain';
        $exceptionReason = 'Bad Request: {"message": "Contact requires extra information (registrant:1-S8qPt3SrqTGcXg6EgMjJR317FQlHKnce8Ng3R)", "type": "ValidationError"}';

        new TemplateFactory()->createOne([
            'slug' => MailDomainCreationFailed::getTemplateSlug(),
        ]);

        $transferResult = new TransferResult(DomainStatus::FAILED->value);
        $transferResult->setReason($failedReason);
        $transferResult->setExceptionMessage($exceptionReason);

        $rtrClientMock = $this->createDomainProviderMock();

        $rtrClientMock->expects(self::once())->method('minimalTransfer')->willReturn($transferResult);

        $domainDeployment = $this->subscription->domainDeployment;

        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);
        $domainDeployment->transfer_secret = 'transfer-code';

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::createStub(Mailer::class),
            logger: $logger = self::createMock(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                sprintf(
                    'Domain registration status: FAI. Reason: %s',
                    $failedReason,
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainDeployment->subscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                ],
            );

        $listener->handle($event);
        $domain = Subscription::where('domain', self::DOMAIN)->firstOrFail();

        self::assertSame(DomainStatus::FAILED->value, $domain->technical_status);
        self::assertNotNull($domainDeployment->last_result_received);
        self::assertSame(
            $exceptionReason,
            $domainDeployment->last_result,
        );
    }

    #[Test]
    public function creationListenerSendsMailOnFail(): void
    {
        self::assertEmailsSend([
            MailDomainCreationFailed::class,
        ]);

        $domainDeployment = $this->subscription->domainDeployment;

        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);
        $domainDeployment->transfer_secret = 'transfer-code';

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::resolve(Mailer::class),
            logger: self::resolve(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $listener->failed($event, new LogicException());
        $domain = Subscription::where('domain', self::DOMAIN)->firstOrFail();

        self::assertSame(TechnicalStatus::FAILED->value, $domain->technical_status);
    }

    #[Test]
    public function registrationDomainFailedExceptionResult(): void
    {
        $failedReason = 'Contact is missing information for the transfer of this domain';
        $exceptionReason = 'Bad Request: {"message": "Contact requires extra information (registrant:1-S8qPt3SrqTGcXg6EgMjJR317FQlHKnce8Ng3R)", "type": "ValidationError"}';

        new TemplateFactory()->createOne([
            'slug' => MailDomainCreationFailed::getTemplateSlug(),
        ]);

        $registrationResult = new RegistrationResult(DomainStatus::FAILED);
        $registrationResult->setReason($failedReason);
        $registrationResult->setExceptionMessage($exceptionReason);

        $mockDomainProvider = $this->createMock(RtrService::class);

        $mockDomainProvider->expects(self::once())->method('minimalRegister')->willReturn($registrationResult);

        $mockDomainProvider->method('setHandle')->willReturnSelf();

        $mockDomainProvider->method('setClient')->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $mockDomainProvider);

        $domainDeployment = $this->subscription->domainDeployment;

        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::createStub(Mailer::class),
            logger: $logger = self::createMock(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                sprintf(
                    'Domain registration status: FAI. Reason: %s',
                    $failedReason,
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainDeployment->subscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                ],
            );

        $listener->handle($event);
        $domain = Subscription::where('domain', self::DOMAIN)->firstOrFail();

        self::assertSame(DomainStatus::FAILED->value, $domain->technical_status);
        self::assertNotNull($domainDeployment->last_result_received);
        self::assertSame(
            $exceptionReason,
            $domainDeployment->last_result,
        );
    }

    #[Test]
    public function transferDomainSuccess(): void
    {
        $transferResult = new TransferResult(DomainStatus::ACTIVE->value);
        $transferResult->setReason(
            '{"domainName":"test-domain.nl","status":"completed","requestedDate":"2022-12-15T00:00:00Z","expiryDate":"2022-12-15T00:00:00Z","type":"IN"}',
        );

        $rtrClientMock = $this->createDomainProviderMock();

        $rtrClientMock->expects(self::once())->method('minimalTransfer')->willReturn($transferResult);

        $domainDeployment = $this->subscription->domainDeployment;

        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);
        $domainDeployment->transfer_secret = 'transfer-code';

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::createStub(Mailer::class),
            logger: self::resolve(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $listener->handle($event);

        $this->subscription->refresh();

        self::assertSame(DomainStatus::ACTIVE->value, $this->subscription->technical_status);
        self::assertNotNull($domainDeployment->last_result_received);
        self::assertSame(
            '{"domainName":"test-domain.nl","status":"completed","requestedDate":"2022-12-15T00:00:00Z","expiryDate":"2022-12-15T00:00:00Z","type":"IN"}',
            $domainDeployment->last_result,
        );
    }

    #[Test]
    public function registerDomainSuccess(): void
    {
        $registrationResult = new RegistrationResult(DomainStatus::ACTIVE);
        $registrationResult->setReason('{"domainName":"test-domain.nl","expiryDate":"2022-12-15T00:00:00Z"}');

        $rtrMock = self::createMock(RtrService::class);

        $rtrMock->method('setHandle')->willReturnSelf();

        $rtrMock->method('setClient')->willReturnSelf();

        $rtrMock->expects(self::once())->method('minimalRegister')->willReturn($registrationResult);

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        $domainDeployment = $this->subscription->domainDeployment;

        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $domainDeployment->dnssec_enabled = true;

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::createStub(Mailer::class),
            logger: self::resolve(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $listener->handle($event);

        $domain = Subscription::where('domain', self::DOMAIN)->firstOrFail();

        self::assertSame(DomainStatus::ACTIVE->value, $domain->technical_status);
        self::assertNotNull($domainDeployment->last_result_received);
        self::assertSame(
            '{"domainName":"test-domain.nl","expiryDate":"2022-12-15T00:00:00Z"}',
            $domainDeployment->last_result,
        );
    }

    #[Test]
    public function registerDomainFailedWithWhois(): void
    {
        Model::preventLazyLoading(false);

        $registrationResult = new RegistrationResult(DomainStatus::FAILED);
        $registrationResult->setReason($failedReason = '{"reason":""}');

        $rtrMock = self::createMock(RtrService::class);

        $rtrMock->method('setHandle')->willReturnSelf();

        $rtrMock->method('setClient')->willReturnSelf();

        $rtrMock->expects(self::once())->method('minimalRegister')->willReturn($registrationResult);

        $this->app->singleton(RtrService::class, fn () => $rtrMock);

        $order = new OrderFactory()->for(new CustomerFactory())->createOne();

        $payment = new PaymentFactory()->createOne([
            'customer_uuid' => $this->subscription->customer->uuid,
            'status' => PaymentStatus::PAID,
        ]);

        $order->payments()->save($payment);
        $order->save();

        new OrderLineItemFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'order_id' => $order->id,
            'domain' => $this->subscription->domain,
            'product_uuid' => $this->subscription->product->uuid,
        ]);
        $domainDeployment = $this->subscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $domainDeployment->transfer_secret = null;
        $domainDeployment->private_whois_enabled = true;
        $domainDeployment->dnssec_enabled = true;

        $event = new CreateDomain(
            self::DOMAIN,
            $this->subscription,
            $domainDeployment,
        );

        $listener = new DomainCreationListener(
            domainService: self::resolve(DomainService::class),
            mailer: self::createStub(Mailer::class),
            logger: $logger = self::createMock(LoggerInterface::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            translator: self::resolve(TranslatorInterface::class),
        );

        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                sprintf(
                    'Domain registration status: FAI. Reason: %s',
                    $failedReason,
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainDeployment->subscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                ],
            );

        $listener->handle($event);
    }

    private function createDomainProviderMock(): MockObject
    {
        $mockDomainProvider = $this->createMock(RtrService::class);

        $mockDomainProvider->method('setHandle')->willReturnSelf();

        $mockDomainProvider->method('setClient')->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $mockDomainProvider);

        return $mockDomainProvider;
    }
}
