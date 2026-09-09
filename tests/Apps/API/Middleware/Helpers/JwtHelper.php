<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware\Helpers;

use JsonException;

trait JwtHelper
{
    /**
     * @param array<mixed> $overrides
     *
     * @throws JsonException
     */
    private function getJwt(array $overrides = []): string
    {
        $jwt = (string) file_get_contents(__DIR__ . '/../data/jwt.json');
        $jwt = json_decode($jwt, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($jwt));

        $jwt = array_replace_recursive($jwt, $overrides);

        return json_encode($jwt, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed> $overrides
     *
     * @throws JsonException
     *
     * @return array<mixed>
     *
     */
    private function getJwtAsArray(array $overrides = []): array
    {
        $array = json_decode($this->getJwt($overrides), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($array));

        return $array;
    }
}
