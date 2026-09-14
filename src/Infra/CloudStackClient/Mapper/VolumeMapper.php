<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\Volume;

/**
 * @implements Mapper<Volume>
 */
class VolumeMapper implements Mapper
{
    public function __invoke(array $data): Volume
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_string($data['domainid']));
        assert(is_string($data['account']));
        assert(is_string($data['type']));
        assert(is_string($data['state']));
        $virtualMachineId = $data['virtualmachineid'] ?? null;
        $diskOfferingId = $data['diskofferingid'] ?? null;

        assert(is_string($virtualMachineId) || is_null($virtualMachineId));
        assert(is_string($diskOfferingId) || is_null($diskOfferingId));

        return new Volume(
            id: $data['id'],
            name: $data['name'],
            domainId: $data['domainid'],
            account: $data['account'],
            type: $data['type'],
            state: $data['state'],
            virtualMachineId: $virtualMachineId,
            diskOfferingId: $diskOfferingId,
        );
    }
}
