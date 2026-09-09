<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CustomersController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;

#[CoversClass(CustomersController::class)]
class RequestDirectDebitTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->customer = new CustomerFactory()->withAddress()->createOne([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'created_at' => CarbonImmutable::now()->subDays(16),
            'has_direct_debit' => false,
        ]);
    }

    #[Test]
    public function success(): void
    {
        $payload = [
            'accountHolder' => 'John',
            'accountNumber' => 'NL18RABO0123459876',
            'consent' => 'true',
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.direct-debit.enable'),
                $payload
            )
            ->assertOk();

        Queue::assertPushed(RequestDirectDebitMandateJob::class);
    }

    #[Test]
    public function customerAlreadyHasDirectDebit(): void
    {
        $this->customer->has_direct_debit = true;
        $this->customer->save();
        $this->customer->refresh();

        $payload = [
            'accountHolder' => 'John',
            'accountNumber' => 'NL18RABO0123459876',
            'consent' => 'true',
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.direct-debit.enable'),
                $payload
            )
            ->assertUnprocessable();

        Queue::assertNotPushed(RequestDirectDebitMandateJob::class);
    }

    #[Test]
    public function wrongIBANSupplied(): void
    {
        $this->customer->has_direct_debit = true;
        $this->customer->save();
        $this->customer->refresh();

        $payload = [
            'accountHolder' => 'John',
            'accountNumber' => 'wrongk',
            'consent' => 'true',
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.direct-debit.enable'),
                $payload
            )
            ->assertUnprocessable();

        Queue::assertNotPushed(RequestDirectDebitMandateJob::class);
    }

    #[Test]
    public function reservedIBANSuppliedWillFail(): void
    {
        $this->customer->has_direct_debit = true;
        $this->customer->save();
        $this->customer->refresh();

        $payload = [
            'accountHolder' => 'Argeweb',
            'accountNumber' => 'NL58RABO0370609239',
            'consent' => 'true',
        ];

        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.direct-debit.enable'),
                $payload
            )
            ->assertUnprocessable();

        Queue::assertNotPushed(RequestDirectDebitMandateJob::class);
    }
}
