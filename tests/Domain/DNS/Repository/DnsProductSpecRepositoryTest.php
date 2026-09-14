<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;

#[CoversClass(DnsProductSpecRepository::class)]
class DnsProductSpecRepositoryTest extends IntegrationTestCase
{
    private DnsProductSpecRepository $dnsProductSpecRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->dnsProductSpecRepository = self::resolve(DnsProductSpecRepository::class);
    }

    #[Test]
    public function isPremiumDnsExpectTrue(): void
    {
        $product = new ProductFactory()
            ->premiumDns()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_IS_PREMIUM->value,
                    'value' => true,
                ]),
            )
            ->createOne();

        self::assertTrue($this->dnsProductSpecRepository->isPremiumDns($product));
    }

    #[Test]
    public function isPremiumDnsExpectFalseWhenValueIsFalse(): void
    {
        $product = new ProductFactory()
            ->premiumDns()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_IS_PREMIUM->value,
                    'value' => false,
                ]),
            )
            ->createOne();

        self::assertFalse($this->dnsProductSpecRepository->isPremiumDns($product));
    }

    #[Test]
    public function isPremiumDnsExpectFalseWhenUndefined(): void
    {
        $product = new ProductFactory()->premiumDns()->createOne();

        self::assertFalse($this->dnsProductSpecRepository->isPremiumDns($product));
    }

    #[Test]
    public function allowDnsRecordEditingExpectTrue(): void
    {
        $product = new ProductFactory()
            ->premiumDns()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_CAN_EDIT_RECORDS->value,
                    'value' => true,
                ]),
            )
            ->createOne();

        self::assertTrue($this->dnsProductSpecRepository->allowDnsRecordEditing($product));
    }

    #[Test]
    public function allowDnsRecordEditingExpectFalseWhenValueIsFalse(): void
    {
        $product = new ProductFactory()
            ->premiumDns()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_CAN_EDIT_RECORDS->value,
                    'value' => false,
                ]),
            )
            ->createOne();

        self::assertFalse($this->dnsProductSpecRepository->allowDnsRecordEditing($product));
    }

    #[Test]
    public function allowDnsRecordEditingExpectFalseWhenUndefined(): void
    {
        $product = new ProductFactory()->premiumDns()->createOne();

        self::assertFalse($this->dnsProductSpecRepository->allowDnsRecordEditing($product));
    }
}
