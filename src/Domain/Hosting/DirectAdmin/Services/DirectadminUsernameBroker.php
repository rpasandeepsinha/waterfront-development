<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DirectAdmin\Services;

use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingUsernameInterface;

class DirectadminUsernameBroker implements HostingUsernameInterface
{
    private const int USERNAME_LENGTH = 10;

    public function generateUsername(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $username = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < self::USERNAME_LENGTH; $i++) {
            $username .= $characters[random_int(0, $max)];
        }

        assert($username !== '');

        return $username;
    }
}
