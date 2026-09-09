<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DirectAdmin;

use Illuminate\Support\Str;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\DeleteResellerPackage;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\ManageResellerPackages;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackageReseller;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminFieldException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminResellerPackageNotFoundException;

readonly class ResellerPackage
{
    public function __construct(private DirectAdminApiInterface $api)
    {
    }

    /**
     * Update an existing package with given settings.
     *
     * @param array<string,string> $packageSettings
     *
     * @throws DirectAdminResellerPackageNotFoundException|DirectAdminFieldException
     */
    public function update(string $packageName, array $packageSettings): DirectAdminCommand
    {
        if (! in_array($packageName, $this->all(), true)) {
            throw new DirectAdminResellerPackageNotFoundException($packageName);
        }

        $packageSettings['packagename'] = $packageName;
        return $this->create($packageSettings);
    }

    /**
     * Retrieve an array with all packages available on the server.
     *
     * @return mixed[] with package names.
     */
    public function all(): array
    {
        $packages = new PackageReseller();

        /** @var PackageReseller $resellerPackageCmd */
        $resellerPackageCmd = $this->api->call($packages);

        return $resellerPackageCmd->getPackages();
    }

    /**
     * Create a package for DirectAdmin users.
     *
     * @param array<string,string> $packageSettings
     *
     * @throws DirectAdminFieldException
     */
    public function create(array $packageSettings = []): DirectAdminCommand
    {
        $cmd = new ManageResellerPackages();

        foreach ($packageSettings as $setting => $value) {
            $setMethod = 'set' . Str::ucfirst(Str::camel($setting));

            if (! method_exists($cmd, $setMethod)) {
                continue;
            }

            $cmd->{$setMethod}($value);
        }

        return $this->api->call($cmd);
    }

    /**
     * Delete packages by name.
     *
     * @param string[] $packages list of package names to be deleted
     */
    public function delete(array $packages): DirectAdminCommand
    {
        $cmd = new DeleteResellerPackage();
        $cmd->setPackages($packages);

        return $this->api->call($cmd);
    }
}
