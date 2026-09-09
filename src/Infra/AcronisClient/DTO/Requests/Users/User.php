<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Requests\Users;

use Symfony\Component\Serializer\Attribute\Groups;
use Waterfront\Infra\AcronisClient\Enums\BusinessType;
use Waterfront\Infra\AcronisClient\Enums\Notifications;

#[Groups(['create'])]
class User
{
    public ?string $idpId;

    public ?string $externalId;

    public ?bool $useExistingIdentity;

    public ?string $originId;

    public ?string $originExternalId;

    public ?string $disableAfter;

    public ?string $language;

    /** @var BusinessType[]|null */
    public ?array $businessTypes;

    /** @var Notifications[]|null */
    public ?array $notifications;

    public function __construct(
        public string $tenantId,
        public string $login,
        public bool $enabled,
        public Contact $contact,
    ) {
    }
}
