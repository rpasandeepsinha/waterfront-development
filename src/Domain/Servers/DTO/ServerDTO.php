<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Servers\Enums\ServerType;

/**
 * The credential properties: a null value means "leave the stored credential untouched".
 */
readonly class ServerDTO
{
    public function __construct(
        public ServerType $type,
        public string $hostname,
        public int $port,
        public bool $useSsl,
        public bool $allowNewWebsites,
        public ?string $name,
        public ?string $owner,
        public ?string $ipv4,
        public ?string $ipv6,
        public ?string $username,
        public ?int $maximumWebsites,
        public ?string $password,
        #[SerializedName('loginkey')]
        public ?string $loginKey,
        public ?string $secretKey,
    ) {
    }
}
