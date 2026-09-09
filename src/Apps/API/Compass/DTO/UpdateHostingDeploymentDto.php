<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

use Waterfront\Domain\Hosting\Models\HostingDeployment;

readonly class UpdateHostingDeploymentDto
{
    public function __construct(
        public HostingDeployment $hostingDeployment,
        public ?string $username,
        public ?int $pleskCustomerId,
        public string $provider,
        public int $serverId,
        public ?int $basekitUserRef,
        public ?int $basekitSiteRef,
        public ?string $mailProvider,
        public ?int $mailServerId,
    ) {
    }
}
