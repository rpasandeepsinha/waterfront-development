<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Packages;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class PackageUser extends DirectAdminCommand
{
    /**
     * @var string API Command to DA
     */
    protected string $command = 'CMD_API_PACKAGES_USER';

    protected bool $useJsonResponse = true;

    protected bool $urlDecode = false;

    protected string $method = 'GET';

    private string $package = '';

    /**
     * @var mixed[] List with available packages
     */
    private array $packageResponse = [];

    /**
     * Override response received.
     *
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $this->packageResponse = $decodedContent;

        $this->succeeded = true;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getPackage(): array
    {
        return $this->packageResponse;
    }

    public function setPackageName(string $packageName): void
    {
        $this->package = $packageName;
    }

    public function getPackageName(): string
    {
        return $this->package;
    }

    /**
     * Get the url for this command to send to the API.
     *
     * @return string the relative url with the command name
     */
    public function getUrl(): string
    {
        return $this->getCommand() . "?json=yes&package={$this->getPackageName()}";
    }
}
