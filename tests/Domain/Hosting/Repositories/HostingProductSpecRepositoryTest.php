<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Repositories\HostingProductSpecRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;

#[CoversClass(HostingProductSpecRepository::class)]
class HostingProductSpecRepositoryTest extends IntegrationTestCase
{
    private HostingProductSpecRepository $hostingProductSpecRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->hostingProductSpecRepository = self::resolve(HostingProductSpecRepository::class);
    }

    #[Test]
    public function allowHostingCouplingExpectTrue(): void
    {
        $product = new ProductFactory()
            ->hostingBrons()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT->value,
                    'value' => true,
                ]),
            )
            ->createOne();

        self::assertTrue($this->hostingProductSpecRepository->allowHostingCoupling($product));
    }

    #[Test]
    public function allowHostingCouplingExpectFalseWhenValueIsFalse(): void
    {
        $product = new ProductFactory()
            ->hostingBrons()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT->value,
                    'value' => false,
                ]),
            )
            ->createOne();

        self::assertFalse($this->hostingProductSpecRepository->allowHostingCoupling($product));
    }

    public function tesAllowHostingCouplingExpectFalseWhenUndefined(): void
    {
        $product = new ProductFactory()->hostingBrons()->createOne();

        self::assertFalse($this->hostingProductSpecRepository->allowHostingCoupling($product));
    }
}
