<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\AzureDataFactory;

use Illuminate\Support\Facades\Http;
use JsonException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ferry\Exceptions\AzureWebhookException;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class AzureDataFactoryJobRequester extends AbstractQueueableJob implements JobRequesterInterface
{
    /**
     * @param string $reference Can be the ADF unique validation reference or the reference customer number.
     */
    public function __construct(
        private readonly AzureDataFactoryMessage $message,
        private readonly string $reference
    ) {
        parent::__construct();
    }

    public function handle(ConfigurationInterface $configuration, LoggerInterface $logger): void
    {
        $apiUrl = sprintf(
            '%s/api/ConsumeFerryResponse',
            $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url')
        );

        $this->logMessages($apiUrl, $logger);

        $apiKey = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_key');

        $response = Http::withHeaders([
            'x-functions-key' => $apiKey,
        ])->post(
            $apiUrl,
            $this->message->toArray()
        );

        if ($response->ok()) {
            $logger->info(
                'Response from ADF is OK',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::REQUEST_URI => $apiUrl,
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->reference,
                    LoggingContextKeys::META => [
                        'reference' => $this->reference,
                    ],
                ]
            );

            return;
        }

        // Response not OK
        try {
            $responseBody = (array) json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $responseBody = $response->body();
        }

        $logger->error(
            'Response from webhook to ADF resulted in an not OK status',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::REQUEST_URI => $apiUrl,
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($this->message->toArray()),
                LoggingContextKeys::RESPONSE_DATA => $responseBody,
                LoggingContextKeys::RESPONSE_CODE => $response->status(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->reference,
                LoggingContextKeys::META => [
                    'reference' => $this->reference,
                ],
            ]
        );

        throw new AzureWebhookException(sprintf(
            'Response from webhook to ADF resulted in an %d with body %s with payload %s',
            $response->status(),
            $response->body(),
            json_encode($this->message->toArray(), JSON_THROW_ON_ERROR)
        ));
    }

    public function getMessageType(): string
    {
        return $this->message->getType();
    }

    /**
     * @return array<int, string>
     */
    public function tags()
    {
        return [
            $this->getQueueName()->value,
            sprintf('ferry-webhook reference: %s', $this->reference),
        ];
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_WEBHOOK;
    }

    protected function getJobId(): string
    {
        return $this->job?->getJobId() ?? 'unknown';
    }

    private function logMessages(string $apiUrl, LoggerInterface $logger): void
    {
        /**
         * We'd rather log a split json string than have an error because the context is too big.
         *
         * @see https://yh-jira.atlassian.net/browse/SWD-10015
         */
        $messageData = $this->message->toArray();
        $messageDataAsJson = json_encode($messageData, JSON_THROW_ON_ERROR);
        $contextLengthLimit = 200000; // the exact value of this isn't 100% clear, so we might change this later

        if (strlen($messageDataAsJson) < $contextLengthLimit) {
            $logger->info(
                'Sending response to ADF on URL',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::REQUEST_URI => $apiUrl,
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->reference,
                    LoggingContextKeys::META => [
                        'reference' => $this->reference,
                        'message' => $messageData,
                    ],
                ]
            );
        } else {
            $messageParts = str_split($messageDataAsJson, $contextLengthLimit);
            $total = count($messageParts);

            foreach ($messageParts as $index => $messagePart) {
                $logger->info(
                    sprintf(
                        'Sending response to ADF on URL (%d / %d)',
                        $index + 1,
                        $total,
                    ),
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                        LoggingContextKeys::REQUEST_URI => $apiUrl,
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->reference,
                        LoggingContextKeys::META => [
                            'reference' => $this->reference,
                            'message' => $messagePart,
                        ],
                    ]
                );
            }
        }
    }
}
