<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message\Handler;

use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorSsoUrl;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class DebtorSsoUrlHandler
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(DebtorSsoUrl $debtorSsoUrlMessage): void
    {
        $customer = $this->customerRepository->findByCustomerNumber($debtorSsoUrlMessage->getCustomerNumber());
        if (! $customer instanceof Customer) {
            $this->logger->error(
                'Unknown customer for customer number {customer.number}',
                [
                    LoggingContextKeys::CUSTOMER_NUMBER => $debtorSsoUrlMessage->getCustomerNumber(),
                ],
            );

            return;
        }

        if (
            $customer->invoice_history_url !== $debtorSsoUrlMessage->getDebtorSsoUrl()
            || $customer->admin_url !== $debtorSsoUrlMessage->getAdminUrl()
        ) {
            if ($customer->invoice_history_url !== $debtorSsoUrlMessage->getDebtorSsoUrl()) {
                $this->logger->info(
                    'Updated customer {customer.number} SSO url',
                    [
                        LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                    ],
                );

                $customer->invoice_history_url = $debtorSsoUrlMessage->getDebtorSsoUrl();
            }

            if ($customer->admin_url !== $debtorSsoUrlMessage->getAdminUrl()) {
                $this->logger->info(
                    'Updated customer {customer.number} admin url',
                    [
                        LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                    ],
                );

                $customer->admin_url = $debtorSsoUrlMessage->getAdminUrl();
            }

            $customer->save();
        }
    }
}
