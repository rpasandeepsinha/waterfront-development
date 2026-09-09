<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Payments\Managers\MollieCustomerManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class SyncCustomerToMollieJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly MollieCustomer $mollieCustomer
    ) {
        parent::__construct();
    }

    public function handle(MollieCustomerManager $mollieCustomerManager, LoggerInterface $logger): void
    {
        $customer = $this->mollieCustomer->refresh()->customer;

        $mollieCustomerUpdateDTO = new MollieCustomerRequestDTO(
            name: $customer->name,
            email: $customer->email,
            locale: $customer->locale,
            metadata: new MollieCustomerMetadataDTO(
                debtorId: $customer->customer_number
            )
        );

        try {
            $mollieCustomerManager->updateCustomer(
                mollieCustomer: $this->mollieCustomer,
                mollieCustomerUpdateDTO: $mollieCustomerUpdateDTO
            );
        } catch (MollieCustomerApiException $exception) {
            $logger->error(sprintf(
                'Unable to sync customer data for customer ID: {%d} to Mollie Customer with Remote ID: {%d}',
                $this->mollieCustomer->customer->id,
                $this->mollieCustomer->mollie_customer_reference_id
            ), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CUSTOMERS;
    }
}
