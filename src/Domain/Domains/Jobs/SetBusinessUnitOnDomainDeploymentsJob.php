<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Carbon\CarbonImmutable;
use DB;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class SetBusinessUnitOnDomainDeploymentsJob extends AbstractQueueableJob
{
    public int $tries = 1;

    /**
     * @param int[] $domainDeploymentIds
     */
    public function __construct(
        private readonly ?int $businessUnitId,
        private readonly array $domainDeploymentIds
    ) {
        parent::__construct();
    }

    public function handle(LoggerInterface $logger): void
    {
        $logger->debug(
            sprintf('Updating Business Unit to id %d on Domain Deployments', $this->businessUnitId),
            [
                LoggingContextKeys::PROVISIONING_TYPE => 'extensions',
                LoggingContextKeys::QUEUE_NAME => $this->queue,
                LoggingContextKeys::META => [
                    'domain_deployment_ids' => $this->domainDeploymentIds,
                    'business_unit_id' => $this->businessUnitId,
                ],
            ]
        );

        $table = new DomainDeployment()->getTable();

        DB::table($table)->whereIn('id', $this->domainDeploymentIds)->update([
            'domain_business_unit_id' => $this->businessUnitId,
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }
}
