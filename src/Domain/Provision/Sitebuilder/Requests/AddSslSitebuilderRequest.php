<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use SensitiveParameter;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class AddSslSitebuilderRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::ADD_SSL_SITEBUILDER;

    public function __construct(
        public UuidInterface $tagUuid,
        public protected(set) UuidInterface $context,
        #[SensitiveParameter]
        public string $privateKey,
        public string $mainCertificate,
    ) {
        $this->tag = $this->tagUuid;
    }
}
