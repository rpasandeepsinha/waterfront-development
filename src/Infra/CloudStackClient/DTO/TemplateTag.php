<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class TemplateTag
{
    public function __construct(
        public string $account,
        public string $domain,
        #[SerializedName('domainid')]
        public string $domainId,
        public string $key,
        #[SerializedName('resourceid')]
        public string $resourceId,
        #[SerializedName('resourcetype')]
        public string $resourceType,
        public string $value,
    ) {
    }
}
