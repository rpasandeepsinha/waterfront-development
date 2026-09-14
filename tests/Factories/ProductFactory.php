<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'name' => $this->faker->name(),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->text(),
            'orderable' => true,
            'weight' => $this->faker->randomNumber(),
        ];
    }

    public function redirect(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->redirect())->state(fn (): array => [
            'name' => ProductType::REDIRECT->value,
            'slug' => ProductType::REDIRECT->value,
        ]);
    }

    public function freeRedirect(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->redirect())->state(fn (): array => [
            'name' => ProductType::FREE_REDIRECT->value,
            'slug' => ProductType::FREE_REDIRECT->value,
        ]);
    }

    public function nlDomain(): ProductFactory
    {
        return $this->for(new ProductGroupFactory()->extension())->state(fn (): array => [
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);
    }

    public function freeDns(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->dns())->state(fn (): array => [
            'name' => ProductType::FREE_DNS->value,
            'slug' => ProductType::FREE_DNS->value,
        ]);
    }

    public function vps(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->vps())->state(fn (): array => [
            'name' => 'vps',
            'slug' => 'vps',
        ]);
    }

    public function ubuntu(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->cloudstackOs())->state(fn (): array => [
            'name' => 'ubuntu',
            'slug' => 'ubuntu',
        ]);
    }

    public function sslSingleDomain(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->ssl())->state(fn (): array => [
            'name' => 'Single Domain',
            'slug' => 'single-domain',
        ]);
    }

    public function premiumDns(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->dns())->state(fn (): array => [
            'name' => ProductType::PREMIUM_DNS->value,
            'slug' => ProductType::PREMIUM_DNS->value,
        ]);
    }

    public function emailStart(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())->state(fn (): array => [
            'name' => ProductType::EMAIL_START->value,
            'slug' => ProductType::EMAIL_START->value,
        ])->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT));
    }

    public function emailMax(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())->state(fn (): array => [
            'name' => ProductType::EMAIL_MAX->value,
            'slug' => ProductType::EMAIL_MAX->value,
        ])->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT));
    }

    public function siteBuilder(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())
            ->state(fn (): array => [
                'name' => 'sitebuilder',
                'slug' => 'sitebuilder',
            ])
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER))
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT));
    }

    public function sitebuilderShop(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())
            ->state(fn (): array => [
                'name' => 'sitebuilder-shop',
                'slug' => 'sitebuilder-shop',
            ])
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER))
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value,
                'value' => 123,
            ]));
    }

    public function provisionSiteBuilder(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())
            ->state(fn (): array => [
                'name' => 'sitebuilder',
                'slug' => 'sitebuilder',
            ])
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER))
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT))
            ->has(
                ProductSpecFactory::new()->state([
                    'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value,
                    'value' => 123,
                ]),
            );
    }

    public function mailOnly(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())
            ->state(fn (): array => [
                'name' => 'MailOnly',
                'slug' => ProductType::MAIL_ONLY->value,
            ])
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER))
            ->has(ProductSpecFactory::new()->enable(ProductSpecName::HOSTING_LEGACY_MAIL_ONLY));
    }

    public function withServicePlus(): ProductFactory
    {
        return $this->has(ProductSpecFactory::new([
            'name' => ProductSpecName::HAS_SERVICE_PLUS,
            'value' => '1',
        ]));
    }

    public function hostingBrons(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())->state(fn (): array => [
            'name' => 'hosting_brons',
            'slug' => 'hosting_brons',
        ]);
    }

    public function hostingGold(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->hosting())->state(fn (): array => [
            'name' => 'hosting_gold',
            'slug' => 'hosting_gold',
        ]);
    }

    public function resellerHostingBrons(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->resellerHosting())->state(fn (): array => [
            'name' => 'Reseller Hosting Brons',
            'slug' => 'reseller-brons',
        ]);
    }

    public function administrationFees(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->other())->state(fn (): array => [
            'name' => 'Administration fees',
            'slug' => ProductType::ADMINISTRATION_FEES->value,
        ]);
    }

    public function backupAcronis(?ProductGroup $productGroup = null): ProductFactory
    {
        return $this->for($productGroup ?? new ProductGroupFactory()->backup())
            ->state(fn (): array => [
                'name' => 'Backup 50',
                'slug' => 'backup-50',
            ])
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_CLOUD_STORAGE_GB->value,
                'value' => 50,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_LOCAL_STORAGE_GB->value,
                'value' => 50,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_MOBILE_DEVICES->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_WORKSTATIONS->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_VMS->value,
                'value' => 0,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_SERVERS->value,
                'value' => 0,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_HOSTING_SERVERS->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_M365_SEATS->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_M365_SHAREPOINT_SITES->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_M365_TEAMS->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_GOOGLE_WORKSPACE_SEATS->value,
                'value' => 1,
            ]))
            ->has(ProductSpecFactory::new()->state([
                'name' => ProductSpecName::ACRONIS_WEBSITES->value,
                'value' => 1,
            ]));
    }
}
