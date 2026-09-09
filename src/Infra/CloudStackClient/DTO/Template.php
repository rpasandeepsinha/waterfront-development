<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Carbon\CarbonImmutable;
use Symfony\Component\Serializer\Attribute\SerializedName;

class Template
{
    /**
     * @param array<TemplateTag> $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        #[SerializedName('displaytext')]
        public string $displayText,
        #[SerializedName('ispublic')]
        public bool $isPublic,
        public CarbonImmutable $created,
        #[SerializedName('isready')]
        public bool $isReady,
        #[SerializedName('passwordenabled')]
        public bool $passwordEnabled,
        public string $format,
        #[SerializedName('isfeatured')]
        public bool $isFeatured,
        #[SerializedName('crossZones')] // Yes this is correct, for some reason cloudstack has this one camelCase :/
        public bool $crossZones,
        #[SerializedName('ostypeid')]
        public string $osTypeId,
        #[SerializedName('ostypename')]
        public string $osTypeName,
        public string $account,
        #[SerializedName('zoneid')]
        public string $zoneId,
        #[SerializedName('zonename')]
        public string $zoneName,
        public int $size,
        #[SerializedName('physicalsize')]
        public int $physicalSize,
        #[SerializedName('templatetype')]
        public string $templateType,
        public string $hypervisor,
        public string $domain,
        #[SerializedName('domainid')]
        public string $domainId,
        #[SerializedName('isextractable')]
        public bool $isExtractable,
        public string $checksum,
        public int $bits,
        #[SerializedName('sshkeyenabled')]
        public bool $sshKeyEnabled,
        #[SerializedName('isdynamicallyscalable')]
        public bool $isDynamicallyScalable,
        #[SerializedName('directdownload')]
        public bool $directDownload,
        #[SerializedName('deployasis')]
        public bool $deployAsIs,
        #[SerializedName('requireshvm')]
        public bool $requiresHvm,
        public array $tags,
        #[SerializedName('hasannotations')]
        public bool $hasAnnotations,
    ) {
    }
}
