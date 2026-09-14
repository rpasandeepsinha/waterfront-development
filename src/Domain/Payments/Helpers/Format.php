<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Helpers;

class Format
{
    /**
     * Converts a string with thousands and decimal separators to a number.
     *
     * If there are multiple dots, keep the standard separators.
     * otherwise if there is no comma or the first comma comes before the first dot,
     * reverse the separators.
     */
    public static function stringToNumber(string $string): float
    {
        $decimalSeparator = ',';
        $thousandSeparator = '.';

        $reverseSeparators = self::reverseSeparators($string);

        if ($reverseSeparators) {
            $decimalSeparator = '.';
            $thousandSeparator = ',';
        }

        $number = 0;
        $decimalsSeperated = explode($decimalSeparator, $string);
        $wholePartial = $decimalsSeperated[0];

        $decimalsPartial = '0';
        if (array_key_exists(1, $decimalsSeperated)) {
            $decimalsPartial = '0.' . $decimalsSeperated[1];
        }

        $thousandsSeperated = explode($thousandSeparator, $wholePartial);
        $thousandsPower = count($thousandsSeperated) - 1;

        foreach ($thousandsSeperated as $numberPartial) {
            $number += (int) $numberPartial * (1000 ** $thousandsPower);
            $thousandsPower--;
        }

        return $number + (float) $decimalsPartial;
    }

    /**
     * Format bytes into readable format.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0';
        }

        if ($bytes < 0) {
            return '-' . self::formatBytes(-$bytes, $precision);
        }

        $base = log($bytes, 1024);
        $suffixes = ['', 'KB', 'MB', 'GB', 'TB'];

        $string = round(1024 ** ($base - floor($base)), $precision) . $suffixes[floor($base)];

        return str_replace('.', ',', $string);
    }

    /**
     * Check whether the comma separator and thousand separator should be reversed.
     */
    private static function reverseSeparators(string $string): bool
    {
        $multipleDots = substr_count($string, '.') > 1;
        if ($multipleDots) {
            return false;
        }

        $multipleCommas = substr_count($string, ',') > 1;
        if ($multipleCommas) {
            return true;
        }

        $commaPosition = strpos($string, ',');
        $dotPosition = strpos($string, '.');
        if ($commaPosition === false || $commaPosition < $dotPosition) {
            return true;
        }

        return false;
    }
}
