<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Waterfront\Domain\Payments\Helpers\Format;

class SpecificationMap
{
    /** @param array<string,string> $specifications */
    public function __construct(
        private readonly array $specifications,
    ) {
    }

    /**
     * Specifications may come from different sources, but all specifications are in string format.
     * This method converts all specifications to their intended format. As this is semi-expensive,
     * use it wisely.
     */
    public function getValue(string $key): string|int|bool|float|null
    {
        $value = $this->specifications[$key] ?? null;

        return match ($this->getDataType($key)) {
            'boolean' => (bool) $value,
            'string' => (string) $value,
            'integer' => (int) $value,
            'bytes' => Format::formatBytes((int) $value),
            default => $value,
        };
    }

    /**
     * Generate a map of all specifications. And type them as well. This is an expensive
     * function, so only use it if you can afford it.
     *
     * @return array<string,string|int|bool|float|null>
     */
    public function toArray(): array
    {
        /** @var array<string,string|int|bool|float|null> $specifications */
        $specifications = new Collection($this->specifications)
            ->mapWithKeys(fn ($value, $key) => [$key => $this->getValue($key)])
            ->toArray();

        return $specifications;
    }

    private function getDataType(string $key): ?string
    {
        $config = Config::get("product-specs.{$key}.type");
        assert(is_string($config) || is_null($config));

        return $config;
    }
}
