<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\MigrateProvisionResultData;

use Illuminate\Support\Facades\DB;
use JsonException;
use Psr\Log\LoggerInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ProvisionResultUpdateJob extends AbstractQueueableJob
{
    /**
     * @param object{id: int, response: string, status: string} $row
     */
    public function __construct(
        public object $row,
        public bool $dryRun
    ) {
        parent::__construct();
    }

    public function handle(LoggerInterface $logger): void
    {
        try {
            $responseData = json_decode($this->row->response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $logger->info(
                sprintf(
                    '[%s] Provision result [%d] has no JSON result, skipping',
                    NovaMigrateProvisionResultDataAction::SLUG,
                    $this->row->id
                ),
                $this->getContext()
            );
            return;
        }

        if (! is_array($responseData)) {
            $logger->info(
                sprintf(
                    '[%s] Provision result [%d] has no valid JSON decoded, skipping',
                    NovaMigrateProvisionResultDataAction::SLUG,
                    $this->row->id
                ),
                $this->getContext(meta: ['response_data' => $responseData])
            );
            return;
        }

        $provisionStatus = $responseData['provisionStatus'] ?? $responseData['status'] ?? $this->row->status;
        $validationResult = $responseData['validationResult'] ?? $responseData['validation_results'] ?? [];

        $responseData['provisionStatus'] = $provisionStatus;
        $responseData['validationResult'] = $validationResult;

        unset($responseData['status'], $responseData['validation_results']);

        try {
            $updatedResponse = json_encode($responseData, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $logger->error(
                sprintf(
                    '[%s] Failed to JSON encode updated response for provision result [%d]',
                    NovaMigrateProvisionResultDataAction::SLUG,
                    $this->row->id
                ),
                $this->getContext(keys: [LoggingContextKeys::EXCEPTION => $e], meta: ['response_data' => $responseData])
            );
            return;
        }

        if ($this->dryRun || $updatedResponse === $this->row->response) {
            return;
        }

        DB::table('provisioning_results')
            ->where('id', $this->row->id)
            ->update(['response' => $updatedResponse]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::ONE_TIME_SCRIPTS;
    }

    /**
     * @param array<LoggingContextKeys, mixed> $keys
     * @param array<string, mixed>             $meta
     *
     * @return array<int|string, mixed>
     */
    private function getContext(array $keys = [], array $meta = []): array
    {
        return [
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaMigrateProvisionResultDataAction::SLUG,
            LoggingContextKeys::META => [
                'dry-run' => $this->dryRun,
            ] + $meta,
        ] + $keys;
    }
}
