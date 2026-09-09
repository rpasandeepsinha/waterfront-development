<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message\Handler;

use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\MandateAnnouncement;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class MandateAnnouncementHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    public function handle(MandateAnnouncement $message): void
    {
        $customer = $this->customerRepository->findByCustomerNumber($message->getCustomerNumber());

        if ($customer === null) {
            $this->logger->error('Failed to retrieve customer required for processing a mandate announcement!', [
                LoggingContextKeys::CUSTOMER_NUMBER => $message->getCustomerNumber(),
                LoggingContextKeys::META => [
                    'message' => $message->toArray(),
                ],
            ]);

            return;
        }

        $customer->has_direct_debit = $message->isActive();
        $customer->save();

        $this->logger->info('Updated customer field "has_direct_debit".', [
            LoggingContextKeys::CUSTOMER_NUMBER => $message->getCustomerNumber(),
            LoggingContextKeys::META => [
                'message' => $message->toArray(),
            ],
        ]);
    }
}
