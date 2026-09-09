<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Support\Helpers\DnsHelper;

class DcvCnameValidatorService
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly PublicSuffixList $publicSuffixList,
        private readonly DnsHelper $dnsHelper
    ) {
    }

    public function isCnameMissingOrIncorrect(DcvDetails $dcv): bool
    {
        $expectedName = self::normalizeFqdn($dcv->dnsRecord);
        $expectedValue = self::normalizeFqdn($dcv->dnsContent);

        $registrable = $this->publicSuffixList
            ->getRules()
            ->resolve($expectedName)
            ->registrableDomain()
            ->toString();

        try {
            $zone = $this->dnsService->getDnsZone($registrable);

            $records = array_filter(
                $zone->getRecords(),
                static fn (DnsRecordInterface $record): bool =>
                $record->getType() === DnsRecordType::CNAME->value
                && self::normalizeFqdn($record->getName()) === $expectedName
            );

            if ($records === []) {
                return true;
            }
            return array_all($records, fn ($record) => ! (strcasecmp(self::normalizeFqdn($record->getContent()), $expectedValue) === 0));
        } catch (DnsZoneNotFoundException) {
            // @ignoreException Intentionally fall back to public DNS when authoritative zone is not found.
        }

        $authoritative = null;
        $additional = null;

        $answersRaw = $this->dnsHelper->dnsGetRecord(
            $expectedName,
            DNS_CNAME,
            $authoritative,
            $additional,
            false
        );

        $answers = is_array($answersRaw) ? $answersRaw : [];

        if ($answers === []) {
            return true;
        }

        foreach ($answers as $answer) {
            $target = rtrim(($answer['target'] ?? ''), '.');
            if (strcasecmp($target, $expectedValue) === 0) {
                return false;
            }
        }

        return true;
    }

    private static function normalizeFqdn(string $name): string
    {
        return rtrim($name, '.');
    }
}
