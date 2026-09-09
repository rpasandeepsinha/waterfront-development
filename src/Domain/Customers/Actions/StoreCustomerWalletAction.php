<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Exceptions\InvalidWalletCreditBalanceException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerWallet;

class StoreCustomerWalletAction
{
    public function execute(Customer $customer, int $walletCreditBalance): void
    {
        if ($walletCreditBalance < 0) {
            throw new InvalidWalletCreditBalanceException('Negative balance is not allowed.');
        }

        if ($customer->wallet()->exists()) {
            // Maybe the customer refunded an amount in the old control panel, so we need to update our wallet.
            /** @var CustomerWallet $wallet */
            $wallet = $customer->wallet;
            $wallet->amount = $walletCreditBalance;
            $wallet->save();
        } elseif ($walletCreditBalance > 0) {
            // Create a new wallet
            $wallet = new CustomerWallet();
            $wallet->customer_id = $customer->id;
            $wallet->amount = $walletCreditBalance;
            $wallet->save();
        }
    }
}
