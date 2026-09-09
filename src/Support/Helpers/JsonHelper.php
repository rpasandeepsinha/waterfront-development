<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

use JsonException;
use stdClass;

class JsonHelper
{
    public static function decodeOrNull(?string $json): ?stdClass
    {
        if ($json === null) {
            return null;
        }

        try {
            $decoded = json_decode($json, false, flags: JSON_THROW_ON_ERROR);
            assert($decoded instanceof stdClass);

            return $decoded;
        } catch (JsonException) {
            return null;
        }
    }
}
