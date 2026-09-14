<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\Enum\DomainStatusEnum;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SearchController;
use Waterfront\Apps\API\Compass\Resources\Enum\SearchType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SearchController::class)]
class SearchDomainTest extends IntegrationTestCase
{
    private Customer $normalCustomer;

    private Subscription $extensionSubscription;

    private Subscription $canceledSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        // Customer who needs help
        $this->normalCustomer = new CustomerFactory()->createOne();
        // Domain subscription
        $extensionProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'Domain',
            'slug' => 'extension',
        ]);
        $extensionProduct = new ProductFactory()->createOne([
            'product_group_id' => $extensionProductGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);
        $this->extensionSubscription = new SubscriptionFactory()
            ->for($this->normalCustomer)
            ->for($extensionProduct)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->canceledSubscription = new SubscriptionFactory()
            ->for($this->normalCustomer)
            ->for($extensionProduct)
            ->administrativeStatusCancelled()
            ->technicalStatus(DomainStatusEnum::STATUS_OK)
            ->createOne();

        // Hosting subscription
        $hostingProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'Hosting',
            'slug' => 'hosting',
        ]);
        $hostingProduct = new ProductFactory()->for($hostingProductGroup)->createOne([
            'name' => 'Basic',
            'slug' => 'hosting_basic',
        ]);
        new SubscriptionFactory()
            ->for($this->normalCustomer)
            ->for($hostingProduct)
            ->createOne();
    }

    #[Test]
    public function searchOnSubscriptionName(): void
    {
        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.domains', [
            'searchterm' => $this->extensionSubscription->domain,
        ]));

        $response->assertOk();
        $response->assertExactJson([
            [
                'domain' => $this->extensionSubscription->domain,
                'subscription_id' => $this->extensionSubscription->id,
                'technical_status' => TechnicalStatus::OK->value,
                'customer_number' => $this->normalCustomer->customer_number,
                'customer_name' => $this->normalCustomer->contact_name,
                'type' => SearchType::DOMAIN->value,
            ],
        ]);
    }

    #[Test]
    public function searchCanceledDomainName(): void
    {
        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.domains', [
            'searchterm' => $this->canceledSubscription->domain,
        ]));

        $response->assertOk();
        $response->assertExactJson([
            [
                'domain' => $this->canceledSubscription->domain,
                'subscription_id' => $this->canceledSubscription->id,
                'technical_status' => DomainStatusEnum::STATUS_OK,
                'customer_number' => $this->normalCustomer->customer_number,
                'customer_name' => $this->normalCustomer->contact_name,
                'type' => SearchType::DOMAIN->value,
            ],
        ]);
    }

    #[Test]
    public function searchOnNonExistingSubscriptionName(): void
    {
        $nonExistingDomain = 'unknown-domain.localtest';

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.domains', [
            'searchterm' => $nonExistingDomain,
        ]));

        $response->assertOk();
        $response->assertExactJson([]);
    }
}
