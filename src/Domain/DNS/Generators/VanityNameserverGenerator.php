<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Generators;

use Waterfront\Domain\DNS\Exceptions\DnsVanityTldCountMismatchException;

class VanityNameserverGenerator
{
    public const VANITY_TLD_COUNT = 3;

    /**
     * @param array<int, string> $vanityTlds
     *
     * @throws DnsVanityTldCountMismatchException
     *
     * @return array<int, string>
     */
    public function generateVanityNames(string $domain, array $vanityTlds): array
    {
        $vanityTldCount = count($vanityTlds);

        if ($vanityTldCount !== self::VANITY_TLD_COUNT) {
            throw new DnsVanityTldCountMismatchException(
                tldsGiven: $vanityTldCount,
                tldsRequired: self::VANITY_TLD_COUNT
            );
        }

        $adlerHash = hexdec(hash('adler32', $domain));

        $vanityNames = [];

        for ($i = 0; $i < self::VANITY_TLD_COUNT; $i++) {
            $nsNumber = max(1, $adlerHash % (255 - $i));

            $vanityNames[] = sprintf(
                'ns%d.%s',
                $nsNumber,
                $vanityTlds[$i]
            );
        }

        return $vanityNames;
    }
}
