<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;

class Extension
{
    private string $name;

    private bool $dnssecAllowed = false;

    public static function create(array $data): Extension
    {
        $data = array_filter($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setDnssecAllowed(string|bool $dnssecAllowed): void
    {
        if (is_bool($dnssecAllowed)) {
            $this->dnssecAllowed = $dnssecAllowed;
        } else {
            $this->dnssecAllowed = $dnssecAllowed === '1';
        }
    }

    public function isDnssecAllowed(): bool
    {
        return $this->dnssecAllowed;
    }
}
