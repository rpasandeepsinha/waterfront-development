<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use SensitiveParameter;
use Waterfront\Domain\Provision\Backup\Interfaces\OfferingItemsRequest;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class UpdateBackupRequest extends BackupProvisionRequest implements OfferingItemsRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::UPDATE_BACKUP;

    public function __construct(
        public UuidInterface $tagUuid,
        #[SensitiveParameter]
        public ?string $password = null,
        public ?float $cloudStorageInGb = null,
        public ?float $localStorageInGb = null,
        public ?int $mobileDevices = null,
        public ?int $workStations = null,
        public ?int $vms = null,
        public ?int $servers = null,
        public ?int $hostingServers = null,
        public ?int $m365Seats = null,
        public ?int $m365SharepointSites = null,
        public ?int $m365Teams = null,
        public ?int $googleWorkspaceSeats = null,
        public ?bool $enableGoogleWorkspaceDrive = null,
        public ?int $websites = null,
    ) {
        $this->tag = $this->tagUuid;
    }
}
