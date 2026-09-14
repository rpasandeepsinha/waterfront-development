<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Models\Reinstall;

abstract class ReInstall
{
    public function __construct(
        private readonly string $main,
        private readonly string $intermediate,
        private readonly string $root,
        private readonly string $commonName,
    ) {
    }

    final public function getCommonName(): string
    {
        return $this->commonName;
    }

    final public function getMain(): string
    {
        return $this->main;
    }

    final public function getIntermediate(): string
    {
        return $this->intermediate;
    }

    final public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * @param array<string, string|int> $data
     */
    abstract public static function fromArray(array $data): self;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
