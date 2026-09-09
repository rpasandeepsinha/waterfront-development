<?php

declare(strict_types=1);

namespace Waterfront\Infra\Configuration;

interface ConfigurationInterface
{
    /**
     * @throws ConfigurationException
     */
    public function getAsString(string $key): string;

    /**
     * @throws ConfigurationException
     */
    public function getAsBoolean(string $key): bool;

    /**
     * @throws ConfigurationException
     */
    public function getAsInteger(string $key): int;

    /**
     * @throws ConfigurationException
     */
    public function getAsFloat(string $key): float;

    /**
     * @return array<mixed>
     */
    public function getAsArray(string $key): array;
}
