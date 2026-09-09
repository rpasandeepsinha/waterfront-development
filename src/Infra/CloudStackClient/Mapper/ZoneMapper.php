<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\Zone;

/**
 * @implements Mapper<Zone>
 */
class ZoneMapper implements Mapper
{
    public function __invoke(array $data): Zone
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_string($data['networktype']));
        assert(is_bool($data['securitygroupsenabled']));
        assert(is_string($data['allocationstate']));
        assert(is_string($data['zonetoken']));
        assert(is_string($data['dhcpprovider']));
        assert(is_bool($data['localstorageenabled']));

        return new Zone(
            id:                    $data['id'],
            name:                  $data['name'],
            networktype:           $data['networktype'],
            securitygroupsenabled: $data['securitygroupsenabled'],
            allocationstate:       $data['allocationstate'],
            zonetoken:             $data['zonetoken'],
            dhcpprovider:          $data['dhcpprovider'],
            localstorageenabled:   $data['localstorageenabled']
        );
    }
}
