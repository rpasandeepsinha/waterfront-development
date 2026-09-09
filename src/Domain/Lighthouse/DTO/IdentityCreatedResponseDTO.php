<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\DTO;

use Ramsey\Uuid\UuidInterface;
use SandwaveIo\LighthouseAuthBase\Identity\VerifiableAddresses;
use Symfony\Component\Serializer\Attribute\SerializedName;

class IdentityCreatedResponseDTO
{
    /**
     * @param VerifiableAddresses[] $verifiableAddresses
     */
    public function __construct(
        #[SerializedName('id')]
        public UuidInterface $uuid,
        public array $verifiableAddresses,
    ) {
    }
}
