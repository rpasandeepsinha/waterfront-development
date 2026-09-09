<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

class IdnHelper
{
    public static function toAscii(string $value): string
    {
        return self::convertLabels(
            $value,
            fn (string $label): bool => ! mb_check_encoding($label, 'ASCII'),
            fn (string $label): string|false => idn_to_ascii($label, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
        );
    }

    private static function convertLabels(string $value, callable $shouldConvert, callable $convert): string
    {
        $labels = explode('.', $value);
        $convertedLabels = [];

        foreach ($labels as $label) {
            if (
                $label === ''
                || $label === '@'
                || $label === '*'
                || str_starts_with($label, '_')
                || ! $shouldConvert($label)
            ) {
                $convertedLabels[] = $label;
                continue;
            }

            $convertedLabel = $convert($label);

            if ($convertedLabel === false) {
                return $value;
            }

            $convertedLabels[] = $convertedLabel;
        }

        return implode('.', $convertedLabels);
    }
}
