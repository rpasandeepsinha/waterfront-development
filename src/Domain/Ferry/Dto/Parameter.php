<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto;

readonly class Parameter
{
    private function __construct(
        private string $key,
        private string|int $value
    ) {
    }

    public static function create(string $key, string|int $value): self
    {
        return new self($key, $value);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): int|string
    {
        return $this->value;
    }
}
