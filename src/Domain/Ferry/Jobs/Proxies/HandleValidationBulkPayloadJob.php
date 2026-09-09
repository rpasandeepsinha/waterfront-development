<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\Proxies;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ferry\Actions\Validation\ExecuteValidationAction;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Jobs\ValidationJob;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class HandleValidationBulkPayloadJob extends AbstractQueueableJob
{
    /**
     * @param array<string, array<mixed>> $validationPayloads
     */
    public function __construct(private readonly array $validationPayloads)
    {
        parent::__construct();
    }

    public function handle(
        Dispatcher $dispatcher,
        LoggerInterface $logger
    ): void {
        $amount = count($this->validationPayloads);

        $logger->info('Starting to insert bulk validation jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'validation_amount' => $amount,
            ],
        ]);

        // Share to pipes from the single flow to ensure the Validation
        // stay's consistent between single and bulk.
        $pipes = ExecuteValidationAction::PIPES;

        foreach ($this->validationPayloads as $validationPayload) {
            // No extra variables for readability to improve memory usage
            $dispatcher->dispatch(
                new ValidationJob(
                    validationPayload: new ValidationPayload(
                        validationReference: $this->getAsString($validationPayload, 'reference'),
                        customer: $this->getAsArray($validationPayload, 'customer'),
                        subscriptions: $this->getAsArray($validationPayload, 'subscriptions')
                    ),
                    pipes: $pipes,
                )
            );
        }

        $logger->info('Completed inserting bulk validation jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'validation_amount' => $amount,
            ],
        ]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_PROXY;
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsString(array $payload, string $key): string
    {
        /** @var string|null $value */
        $value = Arr::get($payload, $key, '');

        // Can still be null if the key exists
        if ($value === null) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private function getAsArray(array $payload, string $key): array
    {
        $value = Arr::get($payload, $key, []);

        if (! is_array($value)) {
            return [];
        }

        return $value;
    }
}
