<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\User;

/**
 * @implements Mapper<User>
 */
class UserMapper implements Mapper
{
    public function __invoke(array $data): User
    {
        assert(is_string($data['id']));
        assert(is_string($data['username']));
        assert(is_string($data['accountid']));
        assert(is_string($data['domainid']));

        return new User(
            id: $data['id'],
            username: $data['username'],
            accountId: $data['accountid'],
            domainId: $data['domainid'],
        );
    }
}
