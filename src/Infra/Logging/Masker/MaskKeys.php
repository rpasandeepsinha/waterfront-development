<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\Masker;

use Waterfront\Infra\Logging\Masker\Interfaces\MaskKeysInterface;

class MaskKeys implements MaskKeysInterface
{
    public function getMaskKeys(): array
    {
        return [
            'url',
            'currentPassword',
            'password',
            'passwd',
            'pass',
            'newPassword',
            'secret',
            'api_key',
            'token',
            'access_token',
            'authorization',
        ];
    }
}
