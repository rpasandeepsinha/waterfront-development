<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

use Illuminate\Support\Facades\App;
use NumberFormatter;

class Money
{
    /**
     * Format money in the dutch format.
     */
    public static function format(
        int|float|string|null $priceInCents = 0,
        ?string $country = null,
        ?string $currency = null,
        ?int $decimals = null,
    ): string {
        $price = intval($priceInCents) / 100;

        $country ??= 'NL';

        $currency ??= 'EUR';

        $decimals ??= 2;

        if (! class_exists('NumberFormatter')) {
            return '&euro; ' . number_format($price, $decimals, ',', '.');
        }

        $formatter = new NumberFormatter(
            App::getLocale() . '_' . $country,
            NumberFormatter::CURRENCY,
        );
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);

        return $formatter->formatCurrency($price, $currency);
    }
}
