<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\DNS\Exceptions\Services\DnsNameserverRetriever\DnsRegionNotFoundException;
use Waterfront\Domain\DNS\Models\DnsNameserver;

class DnsNameserverRetriever
{
    /**
     * Select 1 least used nameservers per region, where $amount is amount of regions selected.
     *
     * @return Collection<int, DnsNameserver> least used nameservers
     */
    public function retrieve(int $amount): Collection
    {
        $nameservers = DnsNameserver::query()
            ->select('id', 'dns_region_id', 'nameserver')
            ->distinct('dns_region_id')
            ->withCount('dnsDeployments')
            ->orderBy('dns_region_id')
            ->orderBy('dns_deployments_count')
            ->limit($amount)
            ->get();

        if ($nameservers->count() < $amount) {
            throw new DnsRegionNotFoundException(
                sprintf(
                    'Requested nameservers from %s region(s), but only %s exist(s)',
                    $amount,
                    $nameservers->count()
                )
            );
        }

        /*
         * According to CLDIN the least used nameservers should be ordered by random
         */
        return $nameservers->shuffle();
    }
}
