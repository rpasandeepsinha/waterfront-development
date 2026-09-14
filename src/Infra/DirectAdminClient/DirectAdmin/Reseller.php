<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DirectAdmin;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use ReflectionException;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\CreateReseller;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ModifyReseller;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerConfig;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminFieldException;

readonly class Reseller
{
    public function __construct(
        private DirectAdminApiInterface $api,
    ) {
    }

    /**
     * Create a new DirectAdmin reseller.
     *
     * @param array<string,string> $resellerData
     *
     * @throws DirectAdminCommandException|DirectAdminFieldException|GuzzleException|ReflectionException
     */
    public function create(array $resellerData): DirectAdminCommand
    {
        $cmd = new CreateReseller();

        foreach ($resellerData as $setting => $value) {
            $setMethod = 'set' . Str::ucfirst(Str::camel($setting));

            if (! method_exists($cmd, $setMethod)) {
                throw new DirectAdminFieldException("Can't set {$setting} on " . $cmd::class);
            }

            $cmd->{$setMethod}($value);
        }

        return $this->api->call($cmd);
    }

    /**
     * Update an existing reseller with given settings.
     *
     * @param array<string,string> $resellerData
     *
     * @throws DirectAdminFieldException
     */
    public function update(string $resellerName, array $resellerData): DirectAdminCommand
    {
        $cmd = new ModifyReseller();
        $cmd->setReseller($resellerName);

        foreach ($resellerData as $setting => $value) {
            $setMethod = 'set' . Str::ucfirst(Str::camel($setting));

            if (! method_exists($cmd, $setMethod)) {
                continue;
            }

            $cmd->{$setMethod}($value);
        }

        return $this->api->call($cmd);
    }

    /**
     * Get all resellers.
     *
     * @return string[] list of resellers
     */
    public function all(): array
    {
        $cmd = new ShowResellers();

        $this->api->call($cmd);

        return $cmd->getResellerList();
    }

    /**
     * Retrieve the reseller's upper limits and settings that defines their account.
     *
     * @throws DirectAdminCommandException|GuzzleException|ReflectionException
     *
     * @return mixed[] Reseller config
     */
    public function showResellerConfig(string $resellerName): array
    {
        $cmd = new ShowResellerConfig();

        $this->api->loginAs($resellerName)->call($cmd);

        return $cmd->getResellerConfig();
    }
}
