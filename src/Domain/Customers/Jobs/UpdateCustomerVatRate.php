<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Jobs;

use Illuminate\Support\Facades\Log;
use SandwaveIo\Vat\Exceptions\VatFetchFailedException;
use SandwaveIo\Vat\Exceptions\VatNumberValidateFailedException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpdateCustomerVatRate extends AbstractQueueableJob
{
    /**
     * Amount of times the job retries.
     */
    public int $tries = 120;

    public function __construct(
        private readonly Customer $customer,
    ) {
        parent::__construct();
    }

    public function handle(CustomerVatService $vatService): void
    {
        /** @var Customer $customer */
        $customer = $this->customer->fresh(['address']);

        try {
            $vatService->updateCustomerVat($customer);
        } catch (VatFetchFailedException|VatNumberValidateFailedException $exception) {
            if ($this->attempts() < $this->tries) {
                $nextDelay = (int) $this->attempts() ** 2;
                $delayHms = gmdate('H:i:s', $nextDelay);

                Log::critical(
                    "Failed to update VAT information for customer $customer->id. "
                    . "Retrying in exactly $delayHms hours, minutes, and seconds ({$this->attempts()}/{$this->tries} attempts). "
                    . "Exception: {$exception->getMessage()}",
                    [
                        LoggingContextKeys::EXCEPTION => $exception,
                    ],
                );

                $this->release($nextDelay);

                return;
            }
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
