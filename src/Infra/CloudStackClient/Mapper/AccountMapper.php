<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\Account;

/**
 * @implements Mapper<Account>
 */
class AccountMapper implements Mapper
{
    public function __invoke(array $data): Account
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_string($data['domainid']));

        return new Account(
            id:       $data['id'],
            name:     $data['name'],
            domainId: $data['domainid'],
        );
    }
}
