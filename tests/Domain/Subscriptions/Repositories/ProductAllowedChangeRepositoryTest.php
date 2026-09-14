<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;

#[CoversClass(ProductAllowedChangeRepository::class)]
class ProductAllowedChangeRepositoryTest extends IntegrationTestCase
{
    private ProductAllowedChangeRepository $repository;

    private Product $freeDnsProduct;

    private Product $basicDnsProduct;

    private Product $superDnsProduct;

    private Product $premiumDnsProduct;

    private Product $supportOnlyDnsProduct;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(ProductAllowedChangeRepository::class);

        $dnsProductGroup = new ProductGroupFactory()->dns()->createOne();

        $this->freeDnsProduct = new ProductFactory()
            ->freeDns($dnsProductGroup)
            ->createOne();

        $this->basicDnsProduct = new ProductFactory()->for($dnsProductGroup)->createOne([
            'name' => 'basicDNS',
            'slug' => 'basic-dns',
        ]);

        $this->superDnsProduct = new ProductFactory()->for($dnsProductGroup)->createOne([
            'name' => 'superDNS',
            'slug' => 'super-dns',
        ]);

        $this->supportOnlyDnsProduct = new ProductFactory()->for($dnsProductGroup)->createOne([
            'name' => 'supportOnlyDNS',
            'slug' => 'support-dns',
        ]);

        $this->premiumDnsProduct = new ProductFactory()
            ->premiumDns($dnsProductGroup)
            ->createOne();
    }

    #[Test]
    public function getPotentialUpgrades(): void
    {
        $this->createUpgrades();

        $potentialUpgrades = $this->repository->getPotentialUpgrades($this->freeDnsProduct);

        self::assertCount(3, $potentialUpgrades);
        self::assertSame($potentialUpgrades->first()?->id, $this->basicDnsProduct->id);
        self::assertSame($potentialUpgrades->last()?->id, $this->superDnsProduct->id);

        $potentialUpgrades = $this->repository->getPotentialUpgrades($this->basicDnsProduct);

        self::assertCount(3, $potentialUpgrades);
        self::assertSame($potentialUpgrades->first()?->id, $this->premiumDnsProduct->id);
        self::assertSame($potentialUpgrades->last()?->id, $this->supportOnlyDnsProduct->id);
    }

    #[Test]
    public function getPotentialUpgradesForCustomer(): void
    {
        $this->createUpgrades();

        $potentialUpgrades = $this->repository->getPotentialUpgradesForCustomer($this->freeDnsProduct);

        self::assertCount(3, $potentialUpgrades);
        self::assertSame($potentialUpgrades->first()?->id, $this->basicDnsProduct->id);
        self::assertSame($potentialUpgrades->last()?->id, $this->superDnsProduct->id);

        $potentialUpgrades = $this->repository->getPotentialUpgradesForCustomer($this->basicDnsProduct);

        self::assertCount(2, $potentialUpgrades);
        self::assertSame($potentialUpgrades->first()?->id, $this->premiumDnsProduct->id);
        self::assertSame($potentialUpgrades->last()?->id, $this->superDnsProduct->id);
    }

    #[Test]
    public function getPotentialDowngrades(): void
    {
        $this->createDowngrades();

        $potentialDowngrades = $this->repository->getPotentialDowngrades($this->premiumDnsProduct);

        self::assertCount(4, $potentialDowngrades);
        self::assertSame($potentialDowngrades->first()?->id, $this->superDnsProduct->id);
        self::assertSame($potentialDowngrades->last()?->id, $this->supportOnlyDnsProduct->id);

        $potentialDowngrades = $this->repository->getPotentialDowngrades($this->basicDnsProduct);

        self::assertCount(1, $potentialDowngrades);
        self::assertSame($potentialDowngrades->first()?->id, $this->freeDnsProduct->id);
        self::assertSame($potentialDowngrades->last()?->id, $this->freeDnsProduct->id);
    }

    #[Test]
    public function getPotentialDowngradesForCustomer(): void
    {
        $this->createDowngrades();

        $potentialDowngrades = $this->repository->getPotentialDowngradesForCustomer($this->premiumDnsProduct);

        self::assertCount(3, $potentialDowngrades);
        self::assertSame($potentialDowngrades->first()?->id, $this->superDnsProduct->id);
        self::assertSame($potentialDowngrades->last()?->id, $this->freeDnsProduct->id);

        $potentialDowngrades = $this->repository->getPotentialDowngradesForCustomer($this->basicDnsProduct);

        self::assertCount(1, $potentialDowngrades);
        self::assertSame($potentialDowngrades->first()?->id, $this->freeDnsProduct->id);
        self::assertSame($potentialDowngrades->last()?->id, $this->freeDnsProduct->id);
    }

    #[Test]
    public function getPotentialUpgradesWithoutExistingPath(): void
    {
        $potentialUpgrades = $this->repository->getPotentialUpgrades($this->freeDnsProduct);
        self::assertCount(0, $potentialUpgrades);
    }

    #[Test]
    public function isProductChangeAllowedTrue(): void
    {
        $this->createUpgrades();

        $isAllowed = $this->repository->isProductChangeAllowed(
            changeType: ProductChangeType::UPGRADE,
            fromProduct: $this->freeDnsProduct,
            toProduct: $this->superDnsProduct,
        );

        self::assertTrue($isAllowed);
    }

    #[Test]
    public function isProductChangeAllowedFalse(): void
    {
        $isAllowed = $this->repository->isProductChangeAllowed(
            changeType: ProductChangeType::UPGRADE,
            fromProduct: $this->freeDnsProduct,
            toProduct: $this->superDnsProduct,
        );

        self::assertFalse($isAllowed);
    }

    private function createUpgrades(): void
    {
        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $this->freeDnsProduct->id,
            'to_product_id' => $this->superDnsProduct->id,
            'display_order' => 3,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $this->freeDnsProduct->id,
            'to_product_id' => $this->premiumDnsProduct->id,
            'display_order' => 2,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $this->freeDnsProduct->id,
            'to_product_id' => $this->basicDnsProduct->id,
            'display_order' => 1,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $this->basicDnsProduct->id,
            'to_product_id' => $this->premiumDnsProduct->id,
            'display_order' => 1,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $this->basicDnsProduct->id,
            'to_product_id' => $this->superDnsProduct->id,
            'display_order' => 2,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChangeSupportOnly()->create([
            'from_product_id' => $this->basicDnsProduct->id,
            'to_product_id' => $this->supportOnlyDnsProduct->id,
            'display_order' => 3,
        ]);
    }

    private function createDowngrades(): void
    {
        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $this->premiumDnsProduct->id,
            'to_product_id' => $this->superDnsProduct->id,
            'display_order' => 1,
        ]);

        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $this->premiumDnsProduct->id,
            'to_product_id' => $this->basicDnsProduct->id,
            'display_order' => 2,
        ]);

        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $this->premiumDnsProduct->id,
            'to_product_id' => $this->freeDnsProduct->id,
            'display_order' => 3,
        ]);

        ProductAllowedChangeFactory::new()->downgradeChangeSupportOnly()->create([
            'from_product_id' => $this->premiumDnsProduct->id,
            'to_product_id' => $this->supportOnlyDnsProduct->id,
            'display_order' => 4,
        ]);

        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $this->basicDnsProduct->id,
            'to_product_id' => $this->freeDnsProduct->id,
        ]);
    }
}
