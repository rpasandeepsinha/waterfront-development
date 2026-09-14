<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Waterfront\Domain\Products\DTO\Configuration\PromotionCallToAction;

/**
 * Normalizes the call_to_action JSON column to snake_case keys on both read and write.
 * Handles legacy camelCase keys (buttonText, destinationUrl, priceDescription) stored before this cast was introduced.
 *
 * @implements CastsAttributes<array<string, string|null>, array<string, string|null>|PromotionCallToAction>
 */
class PromotionCallToActionCast implements CastsAttributes
{
    private const array KEY_MAP = [
        'buttonText' => 'button_text',
        'destinationUrl' => 'destination_url',
        'priceDescription' => 'price_description',
    ];

    /**
     * @param array<mixed> $attributes
     *
     * @return array<string, string|null>|null
     */
    public function get($model, string $key, mixed $value, array $attributes): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $this->normalizeKeys($decoded);
    }

    /**
     * @param array<mixed> $attributes
     */
    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        $array = match (true) {
            $value instanceof PromotionCallToAction => [
                'title' => $value->title,
                'button_text' => $value->buttonText,
                'description' => $value->description,
                'destination_url' => $value->destinationUrl,
                'price_description' => $value->priceDescription,
            ],
            is_array($value) => $this->normalizeKeys($value),
            default => null,
        };

        if ($array === null) {
            return null;
        }

        $encoded = json_encode($array);

        return $encoded !== false ? $encoded : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string|null>
     */
    private function normalizeKeys(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[self::KEY_MAP[$key] ?? $key] = is_string($value) ? $value : null;
        }

        return $normalized;
    }
}
