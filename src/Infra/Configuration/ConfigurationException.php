<?php

declare(strict_types=1);

namespace Waterfront\Infra\Configuration;

use RuntimeException;

class ConfigurationException extends RuntimeException
{
    public static function valueNotSetException(string $key): self
    {
        return new self(sprintf('Value not set for key %s', $key));
    }

    public static function valueIsNotString(string $key, string $actualDatatype): self
    {
        return new self(sprintf('Value for key %s expected to be a string but is %s', $key, $actualDatatype));
    }

    public static function valueIsNotBoolean(string $key): self
    {
        return new self(sprintf('Value for key %s is not a boolean or can be converted to it', $key));
    }

    public static function valueIsNotInteger(string $key): self
    {
        return new self(sprintf('Value for key %s is not an integer or can be converted to it', $key));
    }

    public static function valueIsNotFloat(string $key): self
    {
        return new self(sprintf('Value for key %s is not a float or can be converted to it', $key));
    }

    public static function valueIsNotArray(string $key): self
    {
        return new self(sprintf('Value for key %s is not a array', $key));
    }
}
