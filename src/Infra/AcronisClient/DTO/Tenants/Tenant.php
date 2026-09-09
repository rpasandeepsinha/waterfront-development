<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Tenants;

use Symfony\Component\Serializer\Attribute\Groups;
use Waterfront\Infra\AcronisClient\Enums\Tenants\CustomerType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\ExternalOperationStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\MfaStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;

class Tenant
{
    public ?string $id = null;

    public ?string $createdAt = null;

    public ?string $updatedAt = null;

    public ?string $deletedAt = null;

    public ?string $brandUuid = null;

    public ?string $ownerId = null;

    public bool $hasChildren = false;

    public ?MfaStatus $mfaStatus = null;

    public ?PricingMode $pricingMode = null;

    public ?string $productionStartDate = null;

    /** @var list<Contact> */
    public array $contacts = [];

    #[Groups(['create', 'put'])]
    public ?string $customerId;

    #[Groups(['create', 'put'])]
    public ?string $internalTag;

    #[Groups(['create', 'put'])]
    public ?string $language;

    #[Groups(['create', 'put'])]
    public ?string $defaultIdpId;

    #[Groups(['create', 'put'])]
    public ?bool $ancestralAccess;

    #[Groups(['create'])]
    public ?Settings $settings;

    #[Groups(['create', 'put'])]
    public ?bool $enabled;

    #[Groups(['create', 'put'])]
    public ?UpdateLock $updateLock;

    #[Groups(['put'])]
    public ?CustomerType $customerType;

    #[Groups(['put'])]
    public ?int $brandId;

    public ?ExternalOperationStatus $externalOperationStatus;

    public function __construct(
        #[Groups(['create', 'put'])]
        public string $name,
        #[Groups(['create'])]
        public ?string $parentId,
        #[Groups(['create', 'put'])]
        public TenantType $kind,
        #[Groups(['create', 'put'])]
        public ?Contact $contact,
        #[Groups(['put'])]
        public ?int $version = null,
    ) {
    }
}
