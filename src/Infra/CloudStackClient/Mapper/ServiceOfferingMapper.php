<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\ServiceOffering;

/**
 * @implements Mapper<ServiceOffering>
 */
class ServiceOfferingMapper implements Mapper
{
    public function __invoke(array $data): ServiceOffering
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_int($data['memory']));
        assert(is_int($data['cpunumber']));
        assert(is_string($data['domainid']));
        assert(is_string($data['domain']));
        assert(is_int($data['rootdisksize']));

        return new ServiceOffering(
            id: $data['id'],
            name: $data['name'],
            memory: $data['memory'],
            cpunumber: $data['cpunumber'],
            domainid: $data['domainid'],
            domain: $data['domain'],
            rootdisksize: $data['rootdisksize'],
        );
    }
}
