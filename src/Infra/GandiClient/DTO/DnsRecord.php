<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Infra\GandiClient\Enum\RecordType;

class DnsRecord
{
    /**
     * @param string[] $values
     */
    public function __construct(
        #[SerializedName('rrset_name')]
        public string $name,
        #[SerializedName('rrset_ttl')]
        public int $ttl,
        #[SerializedName('rrset_type')]
        public RecordType $type,
        #[SerializedName('rrset_values')]
        public array $values,
        #[SerializedName('rrset_href')]
        public ?string $href = null,
    ) {
    }
}
