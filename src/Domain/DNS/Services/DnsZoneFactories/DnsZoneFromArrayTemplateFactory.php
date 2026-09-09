<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services\DnsZoneFactories;

use Illuminate\Validation\ValidationException;
use JsonException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsTemplateNotFoundException;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;

class DnsZoneFromArrayTemplateFactory implements DnsZoneFactoryInterface
{
    /**
     * @param array<string, array<array<string, string|int>>> $templates
     */
    public function __construct(
        private readonly array $templates,
        private readonly DnsRecordHydrator $hydrator
    ) {
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws ValidationException
     * @throws JsonException
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
        if (! array_key_exists($dnsTemplate, $this->templates)) {
            throw new DnsTemplateNotFoundException($dnsTemplate);
        }

        $fqdn = new Fqdn($domain);
        $dnsZone = new DnsZone($fqdn, $dnsSec);

        $nameserverHostnames = array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $nameservers);

        $records = json_decode(
            str_replace(
                ['{domain}', '{ipv4}', '{ipv6}', '{ipv4Mail}', '{ipv6Mail}', '{ns1}', '{ns2}', '{ns3}'],
                [$fqdn->toNative(), $ipv4, $ipv6, $ipv4Mail, $ipv6Mail, ...$nameserverHostnames],
                json_encode($this->templates[$dnsTemplate], JSON_THROW_ON_ERROR)
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        assert(is_array($records));
        foreach ($records as $dnsRecord) {
            $dnsZone->addRecord($this->hydrator->hydrate($dnsRecord));
        }

        return $dnsZone;
    }
}
