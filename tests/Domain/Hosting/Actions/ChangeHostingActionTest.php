<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions;

use Carbon\CarbonImmutable;
use DateTime;
use Exception;
use Generator;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Actions\ChangeHostingAction;
use Waterfront\Domain\Hosting\Exceptions\HostingProviderNotFoundException;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Mailer\MailDowngradeProduct;
use Waterfront\Domain\Mailer\MailUpgradeProduct;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

#[CoversClass(ChangeHostingAction::class)]
class ChangeHostingActionTest extends IntegrationTestCase
{
    public const string DOMAIN = 'testdomain.com';

    private Customer $customer;

    private Subscription $subscription;

    private ProductGroup $hostingProductGroup;

    private ChangeHostingAction $changeHostingAction;

    private Provider $provider;

    public function setUp(): void
    {
        parent::setUp();
        $this->changeHostingAction = self::resolve(ChangeHostingAction::class);

        $this->customer = new CustomerFactory()->createOne();

        $this->hostingProductGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::HOSTING,
            'slug' => ProductGroupType::HOSTING,
        ]);

        // "Basic" hosting product.
        new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'name' => 'basic',
        ]);

        // "Premium" hosting product.
        $premiumHostingProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'name' => 'premium',
        ]);

        new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'name' => 'super',
        ]);

        $this->provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);

        // Initial subscription with the "Premium" hosting product.
        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($premiumHostingProduct)
            ->has(
                new HostingDeploymentFactory()->for($this->provider, 'provider')->for(new ServerFactory()),
            )
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => self::DOMAIN,
                'product_uuid' => $premiumHostingProduct->uuid,
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'contract_period' => 12,
            ]);
    }

    /**
     *
     * @param array<string, string|int> $fromProductAttributes
     * @param array<string, string|int> $toProductAttributes
     *
     * @throws ServerNotFoundException
     * @throws SubscriptionChangeException
     * @throws HostingProviderNotFoundException
     */
    #[DataProvider('upgradePriceDataProvider')]
    #[Test]
    public function changeSubscriptionSuccessful(
        int $fromProductPrice,
        int $toProductPrice,
        string $updateAfterXDays,
        array $fromProductAttributes,
        array $toProductAttributes,
        ProductChangeType $changeType,
    ): void {
        Queue::fake();
        CarbonImmutable::setTestNow($updateAfterXDays);

        new TemplateFactory()->createOne([
            'slug' => MailDowngradeProduct::getTemplateSlug(),
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailDowngradeProduct::getTemplateSlug(),
        ]);
        new TemplateFactory()->createOne([
            'slug' => MailUpgradeProduct::getTemplateSlug(),
        ]);
        $fromProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne($fromProductAttributes);

        $toProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne($toProductAttributes);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($fromProduct)
            ->has(
                new HostingDeploymentFactory()->for($this->provider, 'provider')->for(new ServerFactory()),
            )
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => self::DOMAIN,
                'product_uuid' => $fromProduct->uuid,
                // Don't use CarbonImmutable for the 2 dates below, since "now"
                // has been altered at the beginning of the test.
                'start_date' => new DateTime(),
                'end_date' => new DateTime('+1 year'),
                'contract_period' => 12,
            ]);

        Assert::notNull($subscription->hostingDeployment);

        $result = $this->changeHostingAction->execute(
            $subscription,
            $subscription->hostingDeployment,
            $subscription->product,
            $toProduct,
        );

        self::assertSame('ok', $result->status);
    }

    /**
     * @return Generator<mixed>
     */
    public static function upgradePriceDataProvider(): Generator
    {
        // Upgrades.
        yield [
            100,
            110,
            '+1 days',
            ['name' => 'basic'],
            ['name' => 'super'],
            ProductChangeType::UPGRADE,
        ];
        yield [
            1000,
            2000,
            '+1 days',
            ['name' => 'basic'],
            ['name' => 'super'],
            ProductChangeType::UPGRADE,
        ];
        yield [
            1000,
            2000,
            '+2 days',
            ['name' => 'basic'],
            ['name' => 'super'],
            ProductChangeType::UPGRADE,
        ];
        yield [
            1000,
            2000,
            '+100 days',
            ['name' => 'basic'],
            ['name' => 'super'],
            ProductChangeType::UPGRADE,
        ];
        yield [
            5988,
            11988,
            '+92 days',
            ['name' => 'basic'],
            ['name' => 'super'],
            ProductChangeType::UPGRADE,
        ];
        yield [
            5988,
            23988,
            '+5 days',
            ['name' => 'basic'],
            ['name' => 'super'],
            ProductChangeType::UPGRADE,
        ];

        // Downgrades.
        yield [
            110,
            100,
            '+1 days',
            ['name' => 'super'],
            ['name' => 'basic'],
            ProductChangeType::DOWNGRADE,
        ];
        yield [
            2000,
            1000,
            '+1 days',
            ['name' => 'super'],
            ['name' => 'basic'],
            ProductChangeType::DOWNGRADE,
        ];
        yield [
            2000,
            1000,
            '+2 days',
            ['name' => 'super'],
            ['name' => 'basic'],
            ProductChangeType::DOWNGRADE,
        ];
        yield [
            2000,
            1000,
            '+100 days',
            ['name' => 'super'],
            ['name' => 'basic'],
            ProductChangeType::DOWNGRADE,
        ];
        yield [
            11988,
            5988,
            '+92 days',
            ['name' => 'super'],
            ['name' => 'basic'],
            ProductChangeType::DOWNGRADE,
        ];
        yield [
            23988,
            5988,
            '+5 days',
            ['name' => 'super'],
            ['name' => 'basic'],
            ProductChangeType::DOWNGRADE,
        ];
    }

    #[Test]
    public function changeSubscriptionFailedNoHostingProvider(): void
    {
        self::expectException(HostingProviderNotFoundException::class);
        self::expectExceptionMessageIs(sprintf(
            'No hosting provider found for subscription with UUID "%s".',
            $this->subscription->uuid,
        ));

        $this->subscription->hostingDeployment?->update([
            'provider_id' => null,
        ]);

        $hostingDeployment = $this->subscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        $this->changeHostingAction->execute(
            $this->subscription,
            $hostingDeployment,
            $this->subscription->product,
            $this->subscription->product,
        );
    }

    #[Test]
    public function resultShouldBeErrorIfExceptionOccurs(): void
    {
        Queue::fake();

        $fromProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne(['name' => 'super']);

        new ProductFactory()->for($this->hostingProductGroup)->createOne(['name' => 'basic']);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($fromProduct)
            ->has(
                new HostingDeploymentFactory()->for($this->provider, 'provider')->for(new ServerFactory()),
            )
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => self::DOMAIN,
                'product_uuid' => $fromProduct->uuid,
                'end_date' => new CarbonImmutable()->modify('+1 year'),
            ]);

        Assert::notNull($subscription->hostingDeployment);

        $mockHostingServiceFactory = self::mock(HostingServiceFactory::class);
        $mockLogging = self::mock(LoggerInterface::class);

        $mockLogging
            ->expects('info')
            ->once()
            ->with(
                'Changing service plan for subscription {subscription.uuid} to product id {product.id}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => null,
                    LoggingContextKeys::META => [
                        'from_product_slug' => $fromProduct->slug,
                        'from_remote_service_plan' => null,
                        'to_product_slug' => $subscription->product->slug,
                    ],
                ],
            );

        $exception = new Exception('errors during change');

        $mockHostingServiceFactory
            ->shouldReceive('driver->changeServicePlan')
            ->once()
            ->with($subscription->hostingDeployment, $subscription->product, $subscription->product)
            ->andThrow($exception);

        $mockLogging
            ->expects('warning')
            ->once()
            ->with(
                'Technical downgrade or upgrade to product id {product.id} not performed for subscription with id : {subscription.id} (uuid : {subscription.uuid) due to lack of resources. ExceptionMessage : {error.message}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => null,
                    LoggingContextKeys::META => [
                        'from_product_slug' => $fromProduct->slug,
                        'from_remote_service_plan' => null,
                        'to_product_slug' => $subscription->product->slug,
                    ],
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $changeHostingAction = new ChangeHostingAction($mockHostingServiceFactory, $mockLogging);

        $result = $changeHostingAction->execute(
            $subscription,
            $subscription->hostingDeployment,
            $subscription->product,
            $subscription->product,
        );

        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);
        self::assertSame($exception->getCode(), $result->errorCode);
        self::assertSame($exception->getMessage(), $result->errorMessage);
    }

    #[Test]
    public function changeServicePlanUsesExplicitNewProduct(): void
    {
        Queue::fake();

        $newProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne(['name' => 'target-plan']);

        $hostingDeployment = $this->subscription->hostingDeployment;
        Assert::notNull($hostingDeployment);

        $provisioningResult = new Result();
        $provisioningResult->setStatus(Result::STATUS_OK);

        $mockHostingServiceFactory = self::mock(HostingServiceFactory::class);
        $mockHostingServiceFactory
            ->shouldReceive('driver->changeServicePlan')
            ->once()
            ->with($hostingDeployment, $this->subscription->product, $newProduct)
            ->andReturn($provisioningResult);

        $mockLogging = self::mock(LoggerInterface::class);
        $mockLogging->shouldReceive('info');

        $changeHostingAction = new ChangeHostingAction($mockHostingServiceFactory, $mockLogging);

        $result = $changeHostingAction->execute(
            $this->subscription,
            $hostingDeployment,
            $this->subscription->product,
            $newProduct,
        );

        self::assertSame(SubscriptionChangeResult::STATUS_OK, $result->status);
    }
}
