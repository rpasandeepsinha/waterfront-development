<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyCreate\Result as SecretKeyCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyGet\Result as SecretKeyGetResult;

interface SecretKeyInterface extends ClientInterface
{
    public function getSecretKeys(): SecretKeyGetResult;

    public function createSecretKey(string $ipAddress): SecretKeyCreateResult;
}
