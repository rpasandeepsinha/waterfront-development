<?php

declare(strict_types=1);

namespace Tests\Domain\Placeholder\Integration\Services;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Domain\Placeholder\Services\DomainPlaceholderService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotFoundException;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(DomainPlaceholderService::class)]
class DomainPlaceholderServiceTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-domain.nl';

    private Product $product;

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Test .nl',
            'slug' => 'extension_nl',
        ]);
    }

    /**
     * @throws DriverNotFoundException
     */
    #[Test]
    public function register(): void
    {
        self::assertEmailsSend([
            OrderedManualSubscriptionEmployee::class,
        ]);

        $placeholderService = self::resolve(DomainPlaceholderService::class);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $domainDeployment = new DomainDeploymentFactory()
            ->withPlaceholderProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        new TemplateFactory()->createOne([
            'slug' => OrderedManualSubscriptionEmployee::getTemplateSlug(),
        ]);

        $result = $placeholderService->register(
            deployment: $domainDeployment,
            period: 12,
            customer: $this->customer,
            handles: new Handles('test'),
        );

        self::assertSame(DomainStatus::PENDING, $result->getStatus());

        self::assertDatabaseHas('domain_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $domainDeployment->provider->id,
        ]);
    }

    #[Test]
    public function registerFailedMissingProvider(): void
    {
        $placeHolderService = self::resolve(DomainPlaceholderService::class);
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $this->expectException(DriverNotFoundException::class);

        $placeHolderService->register(
            deployment: $domainDeployment,
            period: 12,
            customer: $this->customer,
            handles: new Handles('test'),
        );
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function observerTechnicalStatusSubscription(): void
    {
        $mockMail = self::createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn (): MailerInterface => $mockMail);

        $subscriptionUuid = Str::uuid();
        $customer = new CustomerFactory()->createOne();
        $provider = ProviderFactory::new()->createOne([
            'slug' => ProviderSlug::PLACEHOLDER,
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
        ]);
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($customer)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne([
                'uuid' => $subscriptionUuid,
                'domain' => null,
            ]);
        $domainDeployment = new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
        $subscription->domainDeployment()->save($domainDeployment);
        $subscription->save();

        $mockMail
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(function (array $recipients) use ($customer) {
                    self::assertSame($customer->getEmail(), $recipients[0]->getEmail());
                    self::assertCount(1, $recipients);

                    return true;
                }),
                self::callback(function (MailTemplateInterface $template) {
                    self::assertInstanceOf(ActivatedManualSubscriptionCustomer::class, $template);

                    return true;
                }),
            );

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function observerTechnicalStatusSubscriptionWrongStatus(): void
    {
        $mockMail = self::createMock(MailerInterface::class);
        $mockMail->expects(self::never())->method('send');

        $this->app->bind(MailerInterface::class, fn (): MailerInterface => $mockMail);

        $subscriptionUuid = Str::uuid();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->createOne([
                'uuid' => $subscriptionUuid,
                'domain' => null,
            ]);

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }
}
