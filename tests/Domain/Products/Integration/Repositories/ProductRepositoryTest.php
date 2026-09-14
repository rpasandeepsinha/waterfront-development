<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Repositories\ProductRepository;

#[CoversClass(ProductRepository::class)]
class ProductRepositoryTest extends IntegrationTestCase
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->repository = self::resolve(ProductRepository::class);
    }

    #[Test]
    public function findExistingProductByName(): void
    {
        new ProductGroupFactory()
            ->hosting()
            ->has(new ProductFactory()->state(['name' => 'Foo']))
            ->createOne();

        $product = $this->repository->findProductByName('Foo');
        self::assertSame('Foo', $product->name);

        $this->expectException(ModelNotFoundException::class);
        $this->repository->findProductByName('Bar');
    }

    #[Test]
    public function findProductBySlug(): void
    {
        new ProductGroupFactory()
            ->hosting()
            ->has(new ProductFactory()->state(['slug' => 'foo']))
            ->createOne();

        $product = $this->repository->findProductBySlug('foo');
        self::assertSame('foo', $product->slug);

        $this->expectException(ModelNotFoundException::class);
        $this->repository->findProductByName('bar');
    }

    #[Test]
    public function slugExistsForGroup(): void
    {
        $group = new ProductGroupFactory()
            ->hosting()
            ->has(new ProductFactory())
            ->createOne();
        $productSlug = $group->products->firstOrFail()->slug;

        self::assertTrue($this->repository->slugExistsForGroup($productSlug, $group->slug));
    }

    #[Test]
    public function slugDoesNotExistForGroup(): void
    {
        $group = new ProductGroupFactory()->hosting()->createOne();

        self::assertFalse($this->repository->slugExistsForGroup('nonExistentSlug', $group->slug));
    }
}
