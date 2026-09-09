<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Services;

use Waterfront\Infra\OpenproviderClient\Messages\NameServer;
use Waterfront\Infra\OpenproviderClient\Messages\NameServerCollection;

class NameServerRetriever
{
    public function fetchNameServers(string $domain): ?NameServerCollection
    {
        $nsRecords = dns_get_record($domain, DNS_NS);
        if ($nsRecords === false) {
            return null;
        }

        return new NameServerCollection(...array_map(
            function (array $nsRecord): NameServer {
                $nameServer = new NameServer($nsRecord['target']);
                $ipRecords = dns_get_record($nsRecord['target'], DNS_A + DNS_AAAA);

                if ($ipRecords !== false) {
                    foreach ($ipRecords as $ipRecord) {
                        if ($ipRecord['type'] === 'A') {
                            $nameServer->setIpv4($ipRecord['ip']);
                        } elseif ($ipRecord['type'] === 'AAAA') {
                            $nameServer->setIpv6($ipRecord['ipv6']);
                        }
                    }
                }

                return $nameServer;
            },
            $nsRecords
        ));
    }
}
