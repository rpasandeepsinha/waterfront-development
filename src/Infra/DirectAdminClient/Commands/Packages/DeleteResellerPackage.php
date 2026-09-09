<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Packages;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class DeleteResellerPackage extends DirectAdminCommand
{
    /**
     * More information: https://www.directadmin.com/features.php?id=1298.
     *
     * @var string API Command
     */
    protected string $command = 'CMD_API_MANAGE_RESELLER_PACKAGES';

    protected string $method = 'POST';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * Packages to delete from the DirectAdminServer.
     *
     * @var string[]
     */
    protected array $packages = [];

    /**
     * Add a package to delete.
     *
     * @param string $keyname Name from the DirectAdmin package.
     */
    public function addPackage(string $keyname): DeleteResellerPackage
    {
        $this->packages[] = $keyname;
        return $this;
    }

    /**
     * Set the array with packages to delete.
     *
     * @param string[] $packages Array containing packages from the DirectAdmin server.
     */
    public function setPackages(array $packages): DeleteResellerPackage
    {
        $this->packages = $packages;
        return $this;
    }

    /**
     * Create a 'User Package' request to be send to the api.
     */
    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'delete' => 'Delete Selected',
        ];

        foreach ($this->packages as $index => $keyName) {
            $params['delete' . $index] = $keyName;
        }

        return Utils::streamFor(http_build_query($params));
    }
}
