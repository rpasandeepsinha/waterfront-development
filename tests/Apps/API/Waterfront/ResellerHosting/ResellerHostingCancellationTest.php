<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\ResellerHosting;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversNothing]
class ResellerHostingCancellationTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCancelled::getTemplateSlug(),
        ]);

        $product = new ProductFactory()->for(new ProductGroupFactory()->resellerHosting())->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();
        $this->subscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne();

        new ResellerHostingDeploymentFactory()
            ->for($this->subscription)
            ->for(new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]), 'provider')
            ->create();
    }

    #[Test]
    public function resellerHostingCancellation(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
        ]);
        $parameters = [
            'subscriptions' => [
                [
                    'uuid'        => $this->subscription->uuid,
                    'cancel'      => true,
                    'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                    'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                ],
            ],
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                $parameters
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid'                  => $this->subscription->uuid,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]);
    }

    #[Test]
    public function resellerHostingCancellationUnknownUuid(): void
    {
        $parameters = [
            'subscriptions' => [
                [
                    'uuid'        => '1173c7a4-27f7-11ec-af8b-0242zc120007',
                    'cancel'      => true,
                    'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                    'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                ],
            ],
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                $parameters
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'subscriptions.0.uuid' =>
                    [
                        'Het geselecteerde veld is ongeldig.',
                    ],
            ]);
    }

    #[Test]
    public function resellerHostingCancellationMandatoryMissing(): void
    {
        $parameters = [
            'subscriptions' => [
                [
                    'uuid' => $this->subscription->uuid,
                    'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                ],
            ],
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                $parameters
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' =>
                    [
                        'subscriptions.0.cancel' =>
                            [
                                'Dit veld is verplicht.',
                            ],
                    ],
            ]);
    }
}
