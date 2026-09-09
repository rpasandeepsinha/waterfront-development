<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Services;

use RuntimeException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Servers\Models\Server;
use Webmozart\Assert\Assert;

class MailManagementServerService
{
    public function __construct(
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly HostingDeploymentRepository $deploymentRepository,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function getServer(HostingDeployment $hostingDeployment): Server
    {
        $usesMailOnlyServer = $this->productSpecRepository->booleanSpecificationIsTrue(
            $hostingDeployment->subscription->product,
            ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER
        );

        $server = $usesMailOnlyServer
            ? $hostingDeployment->mailOnlyServer
            : $hostingDeployment->server;

        // Fallback for when product spec gets changed on production
        $server ??= $usesMailOnlyServer
            ? $hostingDeployment->server
            : $hostingDeployment->mailOnlyServer;

        if ($server === null) {
            throw new RuntimeException(
                sprintf(
                    'Hosting Server not set for subscription [%s - %s] with hosting deployment [%s]. Uses MailOnly server: %s. Server ID: [%d] MailOnly Server ID: [%d]',
                    $hostingDeployment->subscription->domain,
                    $hostingDeployment->subscription->uuid,
                    $hostingDeployment->uuid,
                    $usesMailOnlyServer ? 'yes' : 'no',
                    $hostingDeployment->server_id,
                    $hostingDeployment->mail_only_server_id
                )
            );
        }
        return $server;
    }

    /**
     * @throws RuntimeException
     */
    public function findServerByDomain(string $domain): Server
    {
        $subscription = $this->deploymentRepository->getByActiveDomain($domain);
        $deployment = $subscription->hostingDeployment;
        Assert::notNull($deployment);
        return $this->getServer($deployment);
    }
}
