<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DTO;

use Waterfront\Domain\MailManagement\Interfaces\EmailForwardInterface;

readonly class DirectAdminEmailForward implements EmailForwardInterface
{
    /** @param array<string> $destinations */
    public function __construct(
        public string $source,
        public array $destinations,
    ) {
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @returns array<int, string>
     */
    public function getDestinations(): array
    {
        return $this->destinations;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function toArray(string $domain): array
    {
        return [
            $this->getSource() . '@' . $domain => $this->getDestinations(),
        ];
    }
}
