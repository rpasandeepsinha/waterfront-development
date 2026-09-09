<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\ValueObjects;

use Waterfront\Domain\DNS\Exceptions\InvalidValueForValueObjectException;
use Waterfront\Support\Helpers\IdnHelper;

/**
 * Represents a fully qualified domain name, fqdn.
 */
class Fqdn
{
    private readonly string $value;

    final public function __construct(string $value)
    {
        $value = IdnHelper::toAscii($value);

        if (! $this->validValue($value)) {
            throw new InvalidValueForValueObjectException($value, self::class);
        }
        $this->value = $this->sanitizeValue($value);
    }

    public function withoutTrailingDot(): string
    {
        return rtrim($this->toNative(), '.');
    }

    public static function fromNative(mixed $value): self
    {
        assert(is_string($value) || is_int($value) || is_float($value) || is_bool($value));
        return new self(strval($value));
    }

    public function toNative(): string
    {
        return $this->value;
    }

    private function validValue(string $value): bool
    {
        return preg_match('/(?=^.{4,253}$)(^((?!-)[a-z0-9-_]{0,62}[a-z0-9_*]\.)+[a-z]{2,63}\.?$)/i', $value) > 0;
    }

    private function sanitizeValue(string $value): string
    {
        return rtrim(trim($value), '.') . '.';
    }
}
