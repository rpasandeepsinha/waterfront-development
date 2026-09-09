<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;

/**
 * @implements CastsAttributes<IdentityMetadataDTO, IdentityMetadataDTO>
 */
class IdentityMetadataCast implements CastsAttributes
{
    /**
     * @param array<mixed> $attributes
     */
    public function get($model, $key, $value, array $attributes): ?IdentityMetadataDTO
    {
        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (
            ! is_array($decoded) ||
            ! array_key_exists('uuid', $decoded)  ||
            ! array_key_exists('email', $decoded) ||
            ! is_string($decoded['uuid'])  ||
            ! Uuid::isValid($decoded['uuid']) ||
            ! is_string($decoded['email'])
        ) {
            return null;
        }

        return new IdentityMetadataDTO(
            uuid: Uuid::fromString($decoded['uuid']),
            email: $decoded['email'],
        );
    }

    /**
     * @param array<mixed> $attributes
     */
    public function set($model, $key, $value, array $attributes): ?string
    {
        if (! $value instanceof IdentityMetadataDTO) {
            return null;
        }

        $encoded = json_encode([
            'uuid' => $value->uuid->toString(),
            'email' => $value->email,
        ]);

        return $encoded !== false ? $encoded : null;
    }
}
