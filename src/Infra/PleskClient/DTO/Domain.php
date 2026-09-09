<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\DTO;

/**
 * @See https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-plesk-server/getting-server-information/response-packet-structure-and-samples/list-of-domains.75294/
 */
class Domain
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $asciiName,
        public readonly string $type,
        public readonly bool $isMain,
        public readonly string $guid,
        public readonly ?int $externalId,
        public readonly ?int $parentId,
        public readonly ?int $domainId,
    ) {
    }
}
