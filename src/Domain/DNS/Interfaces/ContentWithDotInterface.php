<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Interfaces;

use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;

/**
 * @see PowerDnsZoneToDnsZoneConverter::convertToPowerDnsRecord
 * If a Dns record instance implements this interface this is used and not the getContent method to get the
 * dot at the end.
 */
interface ContentWithDotInterface
{
    public function getContentPlusDot(): string;
}
