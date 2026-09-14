<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Mailers;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class CustomerWalletRefundRequested implements MailTemplateInterface
{
    public function __construct(
        public readonly string $wallet_amount,
        public readonly string $account_holder,
        public readonly string $account_number,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'customer-wallet-refund-requested';
    }
}
