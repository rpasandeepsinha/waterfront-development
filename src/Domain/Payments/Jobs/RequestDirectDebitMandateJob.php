<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Actions\CreateDirectDebitMandateAction;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RequestDirectDebitMandateJob extends AbstractQueueableJob
{
    public int $tries = 5;

    public function __construct(
        private readonly string $accountName,
        private readonly string $accountNumber,
        private readonly ?string $bic,
        private readonly Customer $customer,
        private readonly CarbonImmutable $signDate,
    ) {
        parent::__construct();
    }

    public function failed(?Throwable $exception): void
    {
        $logger = Container::getInstance()->make(LoggerInterface::class);

        if ($exception === null) {
            $logger->critical('Failed to request direct debit for customer.', [
                    LoggingContextKeys::CUSTOMER_ID  => $this->customer->id,
            ]);
            return;
        }

        $logger->critical(
            sprintf('Failed to request direct debit for customer. Job failed with exception message: %s', $exception->getMessage()),
            [
                LoggingContextKeys::CUSTOMER_ID  => $this->customer->id,
                LoggingContextKeys::EXCEPTION => $exception,
            ],
        );
    }

    public function handle(
        CreateDirectDebitMandateAction $createDirectDebitMandateAction,
        LoggerInterface $logger
    ): void {
        try {
            $createDirectDebitMandateAction->execute($this->customer, $this->accountName, $this->accountNumber, $this->signDate, $this->bic);
        } catch (Throwable $exception) {
            $logger->critical(
                sprintf('Error setting up direct debit mandate: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::CUSTOMER_ID  => $this->customer->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw $exception;
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }
}
