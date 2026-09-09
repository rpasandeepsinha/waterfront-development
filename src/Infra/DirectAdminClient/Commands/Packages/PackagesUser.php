<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Packages;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class PackagesUser extends DirectAdminCommand
{
    /**
     * @var string API Command to DA
     */
    protected string $command = 'CMD_API_PACKAGES_USER';

    /**
     * @var mixed[] List with available packages
     */
    private array $packages = [];

    /**
     * Override response received.
     *
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $this->packages = $decodedContent;

        $this->succeeded = true;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }
}
