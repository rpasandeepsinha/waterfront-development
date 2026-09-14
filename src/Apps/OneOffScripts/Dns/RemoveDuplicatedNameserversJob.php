<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Dns;

use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\OneOffScripts\RemoveDuplicatedNameservers\NovaRemoveDuplicatedNamserversAction;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RemoveDuplicatedNameserversJob extends AbstractQueueableJob
{
    public int $tries = 4;

    public function __construct(
        private readonly int $dnsDeploymentId,
        private readonly bool $reassignNameservers = false,
    ) {
        parent::__construct();
    }

    public function handle(
        DnsDeploymentRepository $dnsDeploymentRepository,
        NameserverAssignerFactory $nameserverAssignerFactory,
        LoggerInterface $logger,
    ): void {
        $dnsDeployment = $dnsDeploymentRepository->findById($this->dnsDeploymentId);
        if ($dnsDeployment === null) {
            $logger->warning(
                'Tried deleting duplicated nameservers for dns deployment, while no dns deployment is available.',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                    LoggingContextKeys::PROVISIONING_ID => $this->dnsDeploymentId,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaRemoveDuplicatedNamserversAction::SLUG,
                ],
            );
            $this->delete();

            return;
        }

        $logger->debug(
            'Deleting duplicated nameservers for dns deployment.',
            [
                LoggingContextKeys::DOMAIN_NAME => $dnsDeployment->subscription->domain,
                LoggingContextKeys::PRODUCT_SLUG => $dnsDeployment->subscription->product->slug,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                LoggingContextKeys::PROVISIONING_ID => $dnsDeployment->id,
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaRemoveDuplicatedNamserversAction::SLUG,
            ],
        );

        $this->deleteDuplicatedNameserversByDeploymentId($this->dnsDeploymentId);

        if ($this->reassignNameservers) {
            try {
                $nameserverAssigner = $nameserverAssignerFactory->createAssigner($dnsDeployment->nameserver_type);
                $nameserverAssigner->assign($dnsDeployment);

                // @phpstan-ignore thecodingmachine.emptyCatch
            } catch (DnsNamerverAlreadyAssignedException|FailedToFetchNameserversException) {
            }
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }

    private function deleteDuplicatedNameserversByDeploymentId(int $deploymentId): void
    {
        $query = <<<SQL
        DELETE FROM dns_deployment_dns_nameserver
        WHERE dns_deployment_id = :deployment_id
            AND id IN (
        	SELECT MAX(id)
        	FROM dns_deployment_dns_nameserver
        	GROUP BY dns_nameserver_id, dns_deployment_id
        	HAVING COUNT(1) > 1
        );
        SQL;

        DB::statement($query, ['deployment_id' => $deploymentId]);
    }
}
