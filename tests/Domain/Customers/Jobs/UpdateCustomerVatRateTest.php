<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Jobs;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Jobs\UpdateCustomerVatRate;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerVatError;
use Waterfront\Domain\Customers\Services\CustomerVatService;

#[CoversClass(UpdateCustomerVatRate::class)]
class UpdateCustomerVatRateTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOneQuietly([
            'vat_number' => 'NL861350480B01',
        ]);

        new CustomerAddressFactory()->createQuietly([
            'customer_id' => $this->customer->id,
            'country_code' => 'NL',
        ]);
    }

    #[Test]
    public function vatRateChanged(): void
    {
        $vatRate = 21.0;
        $statusCode = 500;
        $errorMessage = 'errorMessage';

        $job = new UpdateCustomerVatRate($this->customer);
        $job->tries = 1;

        self::assertFalse(Cache::has('vat.rate.NL'));

        self::assertNull($this->customer->vat_rate);

        self::assertDatabaseMissing(CustomerVatError::class, [
            'status_code' => $statusCode,
            'message' => $errorMessage,
        ]);

        $job->handle(
            self::resolve(CustomerVatService::class),
        );

        self::assertSame(21.0, Cache::get('vat.rate.NL'));

        self::assertDatabaseMissing(CustomerVatError::class, [
            'status_code' => $statusCode,
            'message' => $errorMessage,
        ]);

        $this->customer->refresh();

        self::assertFalse($this->customer->icp);
        self::assertSame($vatRate, $this->customer->vat_rate);
    }
}
