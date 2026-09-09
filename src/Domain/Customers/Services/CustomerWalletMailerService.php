<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Services;

use Waterfront\Domain\Customers\Mailers\CustomerWalletRefundRequested;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Support\Helpers\Money;

class CustomerWalletMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function sendRequestConfirmation(CustomerWallet $wallet, string $name, string $number): void
    {
        $this->mailer->send(
            [$wallet->customer],
            new CustomerWalletRefundRequested(
                Money::format($wallet->amount),
                $name,
                substr($number, -4),
            )
        );
    }
}
