<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\VoucherController;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

#[CoversClass(VoucherController::class)]
class VoucherControllerTest extends IntegrationTestCase
{
    private ProductGroup $hostingGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);
    }

    #[Test]
    public function storeVoucherSuccessfully(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.voucher.store'), [
                'displayName'                    => 'Summer Sale',
                'internalName'                   => 'summer-sale-2024',
                'description'                    => 'A summer discount',
                'code'                           => 'SUMMER24',
                'amount'                         => 1000,
                'amountType'                     => VoucherAmountType::FIXED->value,
                'applyWithDiscount'              => false,
                'allowMultipleClaimsSameCustomer' => false,
                'productGroupSlug'               => ProductGroupType::HOSTING->value,
            ])
            ->assertCreated()
            ->assertJsonStructure(['uuid']);

        self::assertDatabaseHas('vouchers', [
            'display_name'       => 'Summer Sale',
            'internal_name'      => 'summer-sale-2024',
            'code'               => 'SUMMER24',
            'amount'             => 1000,
            'amount_type'        => VoucherAmountType::FIXED->value,
            'product_group_uuid' => $this->hostingGroup->uuid,
        ]);
    }

    #[Test]
    public function storeVoucherWithProductSetsProductUuid(): void
    {
        $product = new ProductFactory()->for($this->hostingGroup)->createOne(['slug' => 'hosting-brons']);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.voucher.store'), [
                'displayName'                    => 'Hosting Deal',
                'internalName'                   => 'hosting-deal',
                'code'                           => 'HOSTING10',
                'amount'                         => 500,
                'amountType'                     => VoucherAmountType::FIXED->value,
                'applyWithDiscount'              => false,
                'allowMultipleClaimsSameCustomer' => false,
                'productSlug'                    => 'hosting-brons',
                'productGroupSlug'               => ProductGroupType::HOSTING->value,
            ])
            ->assertCreated();

        self::assertDatabaseHas('vouchers', [
            'code'         => 'HOSTING10',
            'product_uuid' => $product->uuid,
        ]);
    }

    #[Test]
    public function storeVoucherFailsWhenProductDoesNotBelongToGroup(): void
    {
        $extensionGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        new ProductFactory()->for($extensionGroup)->createOne(['slug' => 'extension-nl']);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.voucher.store'), [
                'displayName'                    => 'Bad Voucher',
                'internalName'                   => 'bad-voucher',
                'code'                           => 'BAD10',
                'amount'                         => 100,
                'amountType'                     => VoucherAmountType::FIXED->value,
                'applyWithDiscount'              => false,
                'allowMultipleClaimsSameCustomer' => false,
                'productSlug'                    => 'extension-nl',
                'productGroupSlug'               => ProductGroupType::HOSTING->value,
            ])
            ->assertUnprocessable();

        self::assertDatabaseMissing('vouchers', ['code' => 'BAD10']);
    }

    #[Test]
    public function storeVoucherFailsWhenCodeAlreadyExists(): void
    {
        $existing = new VoucherFactory()->for($this->hostingGroup, 'productGroup')->createOne(['code' => 'DUPLICATE']);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.voucher.store'), [
                'displayName'                    => 'Some Voucher',
                'internalName'                   => 'some-voucher',
                'code'                           => $existing->code,
                'amount'                         => 200,
                'amountType'                     => VoucherAmountType::FIXED->value,
                'applyWithDiscount'              => false,
                'allowMultipleClaimsSameCustomer' => false,
                'productGroupSlug'               => ProductGroupType::HOSTING->value,
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function storeVoucherFailsWhenRequiredFieldsMissing(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.voucher.store'), [])
            ->assertUnprocessable();
    }

    #[Test]
    public function listVouchers(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.voucher.list'))
            ->assertOk();
    }
}
