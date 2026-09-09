<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ProductGroupController;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;

#[CoversClass(ProductGroupController::class)]
class ProductGroupControllerTest extends IntegrationTestCase
{
    private ProductGroup $hostingGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingGroup = new ProductGroupFactory()->hosting()->createOne([
            'name' => 'Hosting',
            'ledger_code' => 8010,
            'default_rate' => 0.2,
        ]);
    }

    #[Test]
    public function listReturnsProductGroupsWithTheirCustomersAndTheDefaultRateAsPercentage(): void
    {
        $customer = new CustomerFactory()->createOne();
        $customer->productGroups()->attach($this->hostingGroup, ['discount' => 10]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.product-group.list'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $this->hostingGroup->uuid,
                'slug' => ProductGroupType::HOSTING->value,
                'name' => 'Hosting',
                'ledgerCode' => 8010,
                'default_rate' => 20,
            ])
            ->assertJsonPath('data.0.customers.0.customer_number', $customer->customer_number);
    }

    #[Test]
    public function listRespectsThePageSizeQueryParameter(): void
    {
        new ProductGroupFactory()->ssl()->createOne();
        new ProductGroupFactory()->dns()->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.product-group.list', ['pageSize' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1);
    }

    #[Test]
    public function showReturnsProductGroupWithCustomerDiscounts(): void
    {
        $customer = new CustomerFactory()->createOne();
        $customer->productGroups()->attach($this->hostingGroup, ['discount' => 10]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.product-group.show', ['productGroup' => $this->hostingGroup->uuid]))
            ->assertOk()
            ->assertJsonPath('uuid', $this->hostingGroup->uuid)
            ->assertJsonPath('default_rate', 20)
            ->assertJsonPath('customers.0.customer_number', $customer->customer_number)
            ->assertJsonPath('customers.0.discount', 10);
    }

    #[Test]
    public function showReturnsNoCustomersWhenNoneAreLinkedToTheProductGroup(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.product-group.show', ['productGroup' => $this->hostingGroup->uuid]))
            ->assertOk()
            ->assertJsonPath('customers', []);
    }
}
