<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Atlantis\Controllers\DomainNameController;
use Waterfront\Domain\Customers\Models\Customer;

#[CoversClass(DomainNameController::class)]
class DomainNameControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();
    }

    #[Test]
    public function requestPremiumPrice(): void
    {
        $postData = [
            'domain' => 'testdomain.com',
        ];

        $response = $this->actingAsCustomer($this->customer)->post(
            'storefront/api/v1/domain-name/request-premium-price',
            $postData,
        );

        $response->assertExactJson(['Price requested']);
        $response->assertOk();
    }

    #[Test]
    public function requestPremiumPriceWithEmptyValues(): void
    {
        $postData = [
            'domain' => '',
        ];

        $response = $this->actingAsCustomer($this->customer)->post(
            'storefront/api/v1/domain-name/request-premium-price',
            $postData,
        );
        $response->assertUnprocessable();
    }

    #[Test]
    public function requestPremiumPriceWithoutValues(): void
    {
        $response = $this->actingAsCustomer($this->customer)->post('storefront/api/v1/domain-name/request-premium-price', []);
        $response->assertUnprocessable();
    }

    #[Test]
    public function requestPremiumPriceWithoutAuthentication(): void
    {
        $response = $this->post('storefront/api/v1/domain-name/request-premium-price', []);
        $response->assertUnauthorized();
    }
}
