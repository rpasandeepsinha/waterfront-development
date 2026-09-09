<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use DateTime;
use RealtimeRegister\Domain\DomainContactCollection;
use Symfony\Component\Serializer\Attribute\SerializedName;

class DomainDetailsDTO
{
    /**
     * @param array<int, string>                    $status
     * @param array<int, string>                    $ns
     * @param array<int, string>                    $childHosts
     * @param array<string, mixed>|null             $zone
     * @param array<int, array<string, mixed>>|null $keyData
     * @param array<int, array<string, mixed>>|null $dsData
     */
    public function __construct(
        public string $domainName,
        public string $registrant,
        public array $status,
        public bool $autoRenew,
        public int $autoRenewPeriod,
        public array $ns,
        public bool $premium,
        public array $childHosts = [],
        public ?string $registry = null,
        public ?string $customer = null,
        public ?bool $privacyProtect = null,
        public ?string $authcode = null,
        public ?string $languageCode = null,
        public ?DateTime $createdDate = null,
        public ?DateTime $updatedDate = null,
        public ?DateTime $expiryDate = null,
        public ?array $zone = null,
        public ?DomainContactCollection $contacts = null,
        public ?array $keyData = null,
        #[SerializedName('ds_data')]
        public ?array $dsData = null,
    ) {
    }
}
