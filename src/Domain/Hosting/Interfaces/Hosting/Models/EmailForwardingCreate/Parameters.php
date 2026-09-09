<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate;

use InvalidArgumentException;
use Waterfront\Support\Traits\HydrateableTrait;

class Parameters
{
    use HydrateableTrait;

    private string $domain;

    private string $sourceEmailAddressUsername;

    /**
     * @var array<int,string>
     */
    private array $destinationEmailAddresses;

    /**
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'domain',
            'sourceEmailAddressUsername',
            'destinationEmailAddresses',
        ];
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Returns the username part of the email address being set. Plesk constructs the mail
     * address by itself using the domain and username part: username@domain.
     */
    public function getSourceEmailAddressUsername(): string
    {
        return $this->sourceEmailAddressUsername;
    }

    /**
     * Expects the username part of the email address. Plesk constructs the mail address by
     * itself using the domain and username part: username@domain.
     */
    public function setSourceEmailAddressUsername(string $sourceEmailAddressUsername): void
    {
        if (str_contains($sourceEmailAddressUsername, '@')) {
            throw new InvalidArgumentException("Expecting the source email address' username. Example: [mymail] from [mymail@sandwaveio.dev]");
        }

        $this->sourceEmailAddressUsername = $sourceEmailAddressUsername;
    }

    /**
     * @return array<int,string>
     */
    public function getDestinationEmailAddresses(): array
    {
        return $this->destinationEmailAddresses;
    }

    /**
     * @param array<int, string> $destinationEmailAddresses
     */
    public function setDestinationEmailAddresses(array $destinationEmailAddresses): void
    {
        $this->destinationEmailAddresses = $destinationEmailAddresses;
    }
}
