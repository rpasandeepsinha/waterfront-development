<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Stubs;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Provision\Backup\Requests\BackupProvisionRequest;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class ProvisionRequestWithNestedData extends BackupProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_BACKUP;

    /**
     * @var list<ProvisionNestedItemDTO>
     */
    public array $users = [];

    #[SerializedName('primary_user')]
    public ?ProvisionNestedItemDTO $primaryUser = null;

    public function __construct(
        public UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
