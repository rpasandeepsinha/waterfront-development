<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DirectAdmin;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use ReflectionException;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\DeleteUserPackage;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\ManageUserPackages;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackagesUser;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackageUser;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminPackagNotFoundException;

class Package
{
    public function __construct(
        private readonly DirectAdminApiInterface $api,
    ) {
    }

    /**
     * Update an existing package with given settings.
     *
     * @param mixed[] $packageSettings
     */
    public function update(string $packageName, array $packageSettings): DirectAdminCommand
    {
        if (! in_array($packageName, $this->all(), true)) {
            throw new DirectAdminPackagNotFoundException("Package {$packageName} doesn't exist on the server.");
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
        $packages = new PackagesUser();
        $packages->setFormValues([]);

        return $this->api->call($packages)->getPackages();
    }

    /**
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws DirectAdminCommandException
     *
     * @return array<mixed>
     */
    public function get(string $packageName): array
    {
        $package = new PackageUser();
        $package->setPackageName($packageName);

        return $this->api->call($package)->getPackage();
    }

    /**
     * Create a package for DirectAdmin users.
     *
     * @param mixed[] $packageSettings
     */
    public function create(array $packageSettings = []): DirectAdminCommand
    {
        $cmd = new ManageUserPackages();

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
        $cmd = new DeleteUserPackage();
        $cmd->setPackages($packages);

        return $this->api->call($cmd);
    }
}
