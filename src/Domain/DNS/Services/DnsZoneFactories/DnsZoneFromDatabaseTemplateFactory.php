<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services\DnsZoneFactories;

use Illuminate\Validation\ValidationException;
use JsonException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\Models\DnsTemplate;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSet;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSetRow;
use Waterfront\Domain\DNS\Services\Serializers\PowerDnsRecordContentSerializer;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;

/**
 * Generated DNS templates from the Database.
 */
class DnsZoneFromDatabaseTemplateFactory implements DnsZoneFactoryInterface
{
    public function __construct(
        private readonly DnsRecordHydrator $hydrator,
        private readonly PowerDnsRecordContentSerializer $serializer,
    ) {
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws JsonException
     * @throws ValidationException
     */
    public function create(
        string $domain,
        string $dnsTemplate,
        ?string $ipv4 = null,
        ?string $ipv6 = null,
        ?string $ipv4Mail = null,
        ?string $ipv6Mail = null,
        bool $dnsSec = false,
        array $nameservers = [],
    ): DnsZone {
        $zone = new DnsZone(new Fqdn($domain), $dnsSec);

        /** @var DnsTemplate $template */
        $template = DnsTemplate::query()->where('slug', '=', $dnsTemplate)->firstOrFail();

        /** @var DnsTemplateRecordSet $recordSet */
        foreach ($template->recordSets as $recordSet) {
            foreach ($recordSet->rows as $row) {
                if ($row->content === '{ipv6}' && $ipv6 === null) {
                    continue;
                } elseif ($row->content === '{ipv4}' && $ipv4 === null) {
                    continue;
                } elseif ($row->content === '{ipv4Mail}' && $ipv4Mail === null) {
                    continue;
                } elseif ($row->content === '{ipv6Mail}' && $ipv6Mail === null) {
                    continue;
                } elseif ($row->content === '{ns3}.' && count($nameservers) < 3) {
                    /*
                     * This check can prevent a third nameserver from being sent to PowerDNS.
                     *
                     * Not all domain deployments have 3 nameservers assigned to them.
                     * Minimum is 2 when using provided nameservers (primary + secondary from env)
                     */
                    continue;
                }

                $convertedDnsRecord = $this->convertToDnsRecord(
                    $zone->getFqdn(),
                    $recordSet,
                    $row,
                    $nameservers,
                    $ipv4,
                    $ipv6,
                    $ipv4Mail,
                    $ipv6Mail,
                );

                if ($convertedDnsRecord->getContent() === '') {
                    continue;
                }

                $zone->addRecord($convertedDnsRecord);
            }
        }

        return $zone;
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws JsonException
     * @throws ValidationException
     */
    private function convertToDnsRecord(
        Fqdn $fqdn,
        DnsTemplateRecordSet $recordSet,
        DnsTemplateRecordSetRow $record,
        array $nameservers,
        ?string $ipv4 = null,
        ?string $ipv6 = null,
        ?string $ipv4Mail = null,
        ?string $ipv6Mail = null,
    ): DnsRecordInterface {
        $data = $this->serializer->unserialize($recordSet->type, $record->content);
        $data['name'] = $recordSet->name;
        $data['disabled'] = false;
        $data['type'] = $recordSet->type;
        $data['ttl'] = $recordSet->ttl;

        $nameserverHostnames = array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $nameservers);

        $data = json_decode(
            str_replace(
                ['{domain}', '{ipv4}', '{ipv6}', '{ipv4Mail}', '{ipv6Mail}', '{ns1}', '{ns2}', '{ns3}'],
                [$fqdn->toNative(), $ipv4, $ipv6, $ipv4Mail, $ipv6Mail, ...$nameserverHostnames],
                json_encode($data, JSON_THROW_ON_ERROR),
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        assert(is_array($data));

        return $this->hydrator->hydrate($data);
    }
}
