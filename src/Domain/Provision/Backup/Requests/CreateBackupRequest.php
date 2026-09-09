<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use SensitiveParameter;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Interfaces\OfferingItemsRequest;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class CreateBackupRequest extends BackupProvisionRequest implements OfferingItemsRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_BACKUP;

    /**
     * @param ?string $username Will be generated if null
     * @param ?string $password Will be generated if null
     */
    public function __construct(
        public UuidInterface $tagUuid,
        public string $email,
        public string $firstname,
        public string $lastname,
        public ?float $cloudStorageInGb = null,
        public ?float $localStorageInGb = null,
        public ?string $username = null,
        #[SensitiveParameter]
        public ?string $password = null,
        public Language $language = Language::ENGLISH,
        public ?int $mobileDevices = null,
        public ?int $workStations = null,
        public ?int $servers = null,
        public ?int $vms = null,
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
