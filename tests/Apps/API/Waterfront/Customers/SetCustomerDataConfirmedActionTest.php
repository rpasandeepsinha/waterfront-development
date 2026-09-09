<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\SetCustomerVerifiedAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerDataConfirmationAction;
use Waterfront\Domain\Customers\Models\Customer;

#[CoversClass(SetCustomerVerifiedAction::class)]
class SetCustomerDataConfirmedActionTest extends IntegrationTestCase
{
    private StoreCustomerDataConfirmationAction $storeCustomerDataConfirmationAction;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeCustomerDataConfirmationAction = self::resolve(StoreCustomerDataConfirmationAction::class);

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function setCustomerDataConfirmedDate(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow();
        $date = new CarbonImmutable();

        $this->storeCustomerDataConfirmationAction->execute($this->customer);

        $this->customer->refresh();
        self::assertNotNull($this->customer->data_last_confirmed_at);
        self::assertSame($date->toW3cString(), $this->customer->data_last_confirmed_at->toW3cString());
    }
}
