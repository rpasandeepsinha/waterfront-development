<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\Role;

/**
 * @implements Mapper<Role>
 */
class RoleMapper implements Mapper
{
    public function __invoke(array $data): Role
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_string($data['description']));
        assert(is_string($data['type']));

        return new Role(
            id:          $data['id'],
            name:        $data['name'],
            description: $data['description'],
            type:        $data['type'],
        );
    }
}
