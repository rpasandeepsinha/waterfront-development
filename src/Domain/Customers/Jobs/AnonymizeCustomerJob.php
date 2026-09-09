<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Admin\Actions\AnonymizeCustomerAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class AnonymizeCustomerJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Customer $customer,
    ) {
        parent::__construct();
    }

    public function handle(AnonymizeCustomerAction $anonymizeCustomerAction, LoggerInterface $logger): void
    {
        try {
            $anonymizeCustomerAction->execute($this->customer);
        } catch (AnonymizeCustomerException $exception) {
            $logger->error('Failed to anonymize customer', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
            ]);
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CUSTOMERS;
    }
}
