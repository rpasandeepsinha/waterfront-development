<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration\Repositories;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;

#[CoversClass(ProductSpecRepository::class)]
class ProductSpecRepositoryTest extends IntegrationTestCase
{
    private ProductSpecRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(ProductSpecRepository::class);
    }

    #[DataProvider('providerTrueTypes')]
    #[Test]
    public function isSpecificationSet(mixed $value): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM,
            'value' => $value,
        ]);

        self::assertTrue($this->repository->booleanSpecificationIsTrue($product, ProductSpecName::DNS_IS_PREMIUM));
    }

    #[DataProvider('providerFalseTypes')]
    #[Test]
    public function isSpecificationSetNotSet(mixed $value): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM,
            'value' => $value,
        ]);

        self::assertFalse($this->repository->booleanSpecificationIsTrue($product, ProductSpecName::DNS_IS_PREMIUM));
    }

    #[Test]
    public function getIntegerValueOfSpecificationReturnsNullWhenNotSet(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        self::assertNull($this->repository->getIntegerValueOfSpecification(
            $product,
            ProductSpecName::DNS_VISIBLE_LOG_LINES,
        ));
    }

    #[DataProvider('providerIntegerSpecValues')]
    #[Test]
    public function getIntegerValueOfSpecificationReturnsExpectedValue(string $input, ?int $expected): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::DNS_VISIBLE_LOG_LINES,
            'value' => $input,
        ]);

        self::assertSame($expected, $this->repository->getIntegerValueOfSpecification(
            $product,
            ProductSpecName::DNS_VISIBLE_LOG_LINES,
        ));
    }

    public static function providerIntegerSpecValues(): Generator
    {
        yield 'positive integer string' => ['42', 42];
        yield 'zero string' => ['0', 0];
        yield 'negative integer string' => ['-5', -5];
        yield 'float string returns null' => ['3.9', null];
        yield 'non-numeric string returns null' => ['not_a_number', null];
    }

    #[Test]
    public function getFloatValueOfSpecificationReturnsNullWhenNotSet(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        self::assertNull($this->repository->getFloatValueOfSpecification(
            $product,
            ProductSpecName::DNS_VISIBLE_LOG_LINES,
        ));
    }

    #[DataProvider('providerFloatSpecValues')]
    #[Test]
    public function getFloatValueOfSpecificationReturnsExpectedValue(string $input, ?float $expected): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::DNS_VISIBLE_LOG_LINES,
            'value' => $input,
        ]);

        self::assertSame($expected, $this->repository->getFloatValueOfSpecification(
            $product,
            ProductSpecName::DNS_VISIBLE_LOG_LINES,
        ));
    }

    public static function providerFloatSpecValues(): Generator
    {
        yield 'positive float string' => ['3.14', 3.14];
        yield 'integer string as float' => ['42', 42.0];
        yield 'zero string' => ['0', 0.0];
        yield 'negative float string' => ['-5.5', -5.5];
        yield 'non-numeric string returns null' => ['not_a_number', null];
    }

    public static function providerTrueTypes(): Generator
    {
        yield 'type : string' => ['1'];
        yield 'type : integer' => [1];
        yield 'type : boolean' => [true];
    }

    public static function providerFalseTypes(): Generator
    {
        yield 'type : string' => ['0'];
        yield 'type : integer' => [0];
        yield 'type : boolean' => [false];
        yield 'string not 0 or 1' => ['yes'];
    }
}
