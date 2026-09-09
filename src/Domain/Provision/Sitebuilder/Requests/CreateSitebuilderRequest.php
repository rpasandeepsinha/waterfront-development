<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class CreateSitebuilderRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_SITEBUILDER;

    /**
     * @param int[] $packages
     */
    public function __construct(
        public readonly string $domain,
        public readonly array $packages,
        public readonly string $firstname,
        public readonly string $lastname,
        public readonly string $email,
        public readonly int $contractPeriod,
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
