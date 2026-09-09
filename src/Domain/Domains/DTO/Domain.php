<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use InvalidArgumentException;
use Stringable;

class Domain implements Stringable
{
    private readonly string $extension;

    private readonly string $name;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $domain)
    {
        $parts = explode('.', $domain);

        if (count($parts) < 2) {
            throw new InvalidArgumentException('Invalid domain: ' . $domain);
        }

        /** @var string $name */
        $name = array_shift($parts);

        $this->name = $name;
        $this->extension = implode('.', $parts);
    }

    public function __toString(): string
    {
        return $this->name . '.' . $this->extension;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }
}
