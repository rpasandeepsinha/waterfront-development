<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Events;

use Waterfront\Domain\Ssl\Models\SslDeployment;

class CreateSsl
{
    public function __construct(
        public readonly string $domain,
        public readonly int $period,
        public readonly SslDeployment $sslDeployment,
        public readonly ?string $csr,
    ) {
    }
}
