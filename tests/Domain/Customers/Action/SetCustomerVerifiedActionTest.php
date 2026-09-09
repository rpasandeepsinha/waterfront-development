<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\SetCustomerVerifiedAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Harbor;

#[CoversClass(SetCustomerVerifiedAction::class)]
class SetCustomerVerifiedActionTest extends IntegrationTestCase
{
    private SetCustomerVerifiedAction $setCustomerVerifiedAction;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCustomerVerifiedAction = self::resolve(SetCustomerVerifiedAction::class);

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function setCustomerVerified(): void
    {
        Queue::fake();

        $this->setCustomerVerifiedAction->execute($this->customer);

        $harbor = self::createStub(Harbor::class);
        $this->app->singleton(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);

        self::assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'is_verified' => true,
        ]);
    }
}
