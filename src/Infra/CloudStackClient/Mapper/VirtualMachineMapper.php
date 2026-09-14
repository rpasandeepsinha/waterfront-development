<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;

/**
 * @implements Mapper<VirtualMachine>
 */
class VirtualMachineMapper implements Mapper
{
    public function __invoke(array $data): VirtualMachine
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_string($data['domainid']));
        assert(is_string($data['account']));
        assert(is_string($data['username']));
        assert(is_array($data['nic']));
        assert(is_string($data['state']));
        assert(is_string($data['serviceofferingid']));
        $password = $data['password'] ?? null;
        assert(is_string($password) || is_null($password));

        return new VirtualMachine(
            id: $data['id'],
            name: $data['name'],
            domainId: $data['domainid'],
            account: $data['account'],
            username: $data['username'],
            nic: $data['nic'],
            state: CloudstackMachineState::from($data['state']),
            serviceOfferingId: $data['serviceofferingid'],
            password: $password,
        );
    }
}
