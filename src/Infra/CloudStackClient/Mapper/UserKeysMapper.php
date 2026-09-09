<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\UserKeys;

/**
 * @implements Mapper<UserKeys|null>
 */
class UserKeysMapper implements Mapper
{
    public function __invoke(array $data): ?UserKeys
    {
        if (! array_key_exists('apikey', $data) || ! array_key_exists('secretkey', $data)) {
            return null;
        }

        assert(is_string($data['apikey']));

        return new UserKeys(
            apiKey:    $data['apikey'],
            secretKey: $data['secretkey'],
        );
    }
}
