<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\RemoveStrayParkingDns;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RemoveStrayParkingDnsJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly string $domain,
        private readonly bool $dryRun,
    ) {
        parent::__construct();
    }

    public function handle(
        HostingDeploymentRepository $hostingDeploymentRepository,
        DnsService $dnsService,
        LoggerInterface $logger,
    ): void {
        if (! $hostingDeploymentRepository->hasHostingDeploymentForDomain($this->domain)) {
            return;
        }

        if ($this->dryRun) {
            $conflictingRecords = $dnsService->getConflictingParkingRecords($this->domain);
            if ($conflictingRecords === []) {
                return;
            }

            $logger->notice('Dry run: found conflicting parking DNS records on hosted domain', [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaRemoveStrayParkingDnsAction::SLUG,
                LoggingContextKeys::META => [
                    'records' => $conflictingRecords,
                    'count' => count($conflictingRecords),
                ],
            ]);

            return;
        }

        $removedRecords = $dnsService->removeConflictingParkingRecords($this->domain);
        if ($removedRecords === []) {
            return;
        }

        $logger->notice('Removed conflicting parking DNS records from hosted domain', [
            LoggingContextKeys::DOMAIN_NAME => $this->domain,
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaRemoveStrayParkingDnsAction::SLUG,
            LoggingContextKeys::META => [
                'records' => $removedRecords,
                'count' => count($removedRecords),
            ],
        ]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::ONE_TIME_SCRIPTS;
    }
}
