<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Interfaces;

/**
 * As there is no directory yet in the infra domain for mail only we made the active
 * choice to put the interface here and to not have a new folder
 * just for Domain Driven Design purpose with this interface.
 */
interface EmailForwardInterface
{
    public function getSource(): string;

    /**
     * @return array<int, string>
     */
    public function getDestinations(): array;

    /**
     * @return array<string, array<int, string>>
     */
    public function toArray(string $domain): array;
}
