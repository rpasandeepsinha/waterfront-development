<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\StoreCustomerWalletAction;
use Waterfront\Domain\Customers\Exceptions\InvalidWalletCreditBalanceException;
use Waterfront\Domain\Customers\Models\CustomerWallet;

#[CoversClass(StoreCustomerWalletAction::class)]
class StoreCustomerWalletActionTest extends IntegrationTestCase
{
    #[Test]
    public function itCreateCustomerWalletSuccessfully(): void
    {
        $customer = new CustomerFactory()->createOne([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'customer@example.com',
        ]);

        $walletAmount = 1;

        $action = new StoreCustomerWalletAction();

        $action->execute($customer, $walletAmount);
        $customerWallet = CustomerWallet::query()->where('customer_id', $customer->id)->first();

        self::assertNotNull($customerWallet);
        self::assertSame($walletAmount, $customerWallet->amount);
    }

    #[Test]
    public function itThrowsInvalidWalletCreditBalanceException(): void
    {
        $customer = new CustomerFactory()->createOne([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'customer@example.com',
        ]);
        $walletAmount = -1;

        $action = new StoreCustomerWalletAction();

        $this->expectException(InvalidWalletCreditBalanceException::class);
        $action->execute($customer, $walletAmount);
    }

    #[Test]
    public function itDoesNotCreateWalletOnZeroAmount(): void
    {
        $customer = new CustomerFactory()->createOne([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'customer@example.com',
        ]);
        $walletAmount = 0;

        $action = new StoreCustomerWalletAction();

        $action->execute($customer, $walletAmount);
        $customerWallet = CustomerWallet::query()->where('customer_id', $customer->id)->first();
        self::assertNull($customerWallet);
    }
}
