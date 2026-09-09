<?php

declare(strict_types=1);

namespace Waterfront\Infra\Configuration;

use Illuminate\Config\Repository;

class Configuration implements ConfigurationInterface
{
    public function __construct(private readonly Repository $laravelConfig)
    {
    }

    /**
     * @throws ConfigurationException
     */
    public function getAsString(string $key): string
    {
        $value = $this->laravelConfig->get($key);

        if (is_null($value)) {
            throw ConfigurationException::valueNotSetException($key);
        }

        if (! is_string($value)) {
            throw ConfigurationException::valueIsNotString($key, gettype($value));
        }

        return $value;
    }

    /**
     * @throws ConfigurationException
     */
    public function getAsBoolean(string $key): bool
    {
        $value = $this->laravelConfig->get($key);

        if (is_null($value)) {
            throw ConfigurationException::valueNotSetException($key);
        }

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) === null) {
            throw ConfigurationException::valueIsNotBoolean($key);
        }

        if (is_bool($value)) {
            return $value;
        }

        return (bool) $value;
    }

    /**
     * @throws ConfigurationException
     */
    public function getAsInteger(string $key): int
    {
        $value = $this->laravelConfig->get($key);

        if (is_null($value)) {
            throw ConfigurationException::valueNotSetException($key);
        }

        if (is_integer($value)) {
            return $value;
        }

        if (! is_string($value) && ! is_float($value)) {
            throw ConfigurationException::valueIsNotInteger($key);
        }

        if (! is_numeric($value) && $value != (int) $value) {
            throw ConfigurationException::valueIsNotInteger($key);
        }

        return (int) $value;
    }

    /**
     * @throws ConfigurationException
     */
    public function getAsFloat(string $key): float
    {
        $value = $this->laravelConfig->get($key);

        if (is_null($value)) {
            throw ConfigurationException::valueNotSetException($key);
        }

        if (is_float($value)) {
            return $value;
        }

        if (! is_numeric($value) || $value != (float) $value) {
            throw ConfigurationException::valueIsNotFloat($key);
        }

        return (float) $value;
    }

    /**
     * @return array<mixed>
     */
    public function getAsArray(string $key): array
    {
        $value = $this->laravelConfig->get($key);

        if (is_null($value)) {
            throw ConfigurationException::valueNotSetException($key);
        }

        if (! is_array($value)) {
            throw ConfigurationException::valueIsNotArray($key);
        }

        return $value;
    }
}
