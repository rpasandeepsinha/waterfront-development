<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Domains;

use Waterfront\Domain\Providers\Enums\ProviderSlug;

readonly class DomainMigrationPayload
{
    public function __construct(
        public ?string $referenceDnsTemplateId,
        public string $referenceSubscriptionId,
        public ProviderSlug $driver,
        public ?string $referenceDomainProviderBusinessUnitSlug = null,
    ) {
    }
}
