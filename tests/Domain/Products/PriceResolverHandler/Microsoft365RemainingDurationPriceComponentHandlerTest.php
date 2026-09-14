<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\Microsoft365RemainingDurationComponentPriceHandler;

#[CoversClass(Microsoft365RemainingDurationComponentPriceHandler::class)]
class Microsoft365RemainingDurationPriceComponentHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function handleWithNonM365ProductsShouldCallNextHandler(): void
    {
        $microsoft365Repository = self::createMock(Microsoft365Repository::class);
        $microsoft365Repository->expects(self::never())->method('hasMicrosoft365Subscriptions');

        $m365Handler = new Microsoft365RemainingDurationComponentPriceHandler($microsoft365Repository);

        $nonM365Product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $productPriceRequest = new RegistrationPriceRequest($nonM365Product);

        $m365Handler->handle(new Collection([]), [$productPriceRequest], new CustomerFactory()->createOne());
    }

    #[Test]
    public function handleWithM365ProductsShouldCheckIfCustomerHasM365(): void
    {
        $microsoft365Repository = self::createMock(Microsoft365Repository::class);
        $microsoft365Repository->expects(self::once())->method('hasMicrosoft365Subscriptions');

        $m365Handler = new Microsoft365RemainingDurationComponentPriceHandler($microsoft365Repository);

        $nonM365Product = new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne();
        $productPriceRequest = new RegistrationPriceRequest($nonM365Product);

        $m365Handler->handle(new Collection([]), [$productPriceRequest], new CustomerFactory()->createOne());
    }
}
