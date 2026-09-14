<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DirectAdmin;

use Illuminate\Support\Str;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Users\CreateUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ModifyUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowAllUsers;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserConfig;
use Waterfront\Infra\DirectAdminClient\Commands\Users\SuspendUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\UnsuspendUser;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminFieldException;

class User
{
    public function __construct(
        private readonly DirectAdminApiInterface $api,
    ) {
    }

    /**
     * Create a new DirectAdmin user.
     *
     * @param mixed[] $userData
     */
    public function create(array $userData): DirectAdminCommand
    {
        $cmd = new CreateUser();

        foreach ($userData as $setting => $value) {
            $setMethod = 'set' . Str::ucfirst(Str::camel($setting));

            if (! method_exists($cmd, $setMethod)) {
                continue;
            }

            $cmd->{$setMethod}($value);
        }

        return $this->api->call($cmd);
    }

    /**
     * Update an existing user with given settings.
     *
     * @param mixed[] $userData
     *
     * @throws DirectAdminFieldException
     */
    public function update(string $userName, array $userData): DirectAdminCommand
    {
        $cmd = new ModifyUser();
        $cmd->setUser($userName);

        foreach ($userData as $setting => $value) {
            $setMethod = 'set' . Str::ucfirst(Str::camel($setting));

            if (! method_exists($cmd, $setMethod)) {
                continue;
            }

            $cmd->{$setMethod}($value);
        }

        return $this->api->call($cmd);
    }

    /**
     * Get all users.
     *
     * @return mixed[] list of users
     */
    public function all(): array
    {
        $cmd = new ShowAllUsers();

        $this->api->call($cmd);

        return $cmd->getUserList();
    }

    /**
     * Retrieve the user's upper limits and settings that defines their account.
     *
     * @return mixed[]
     */
    public function showUserConfig(string $userName): array
    {
        $cmd = new ShowUserConfig();

        $this->api->loginAs($userName)->call($cmd);

        return $cmd->getUserConfig();
    }

    /**
     * Retrieve the user's upper limits and settings that defines their account.
     *
     * Please only use this function for migrations!!!
     *
     * @return mixed[]
     */
    public function showUserConfigAsAdmin(string $userName): array
    {
        $cmd = new ShowUserConfig();
        $cmd->setUser($userName);

        $this->api->call($cmd);

        return $cmd->getUserConfig();
    }

    public function suspend(string $user): DirectAdminCommand
    {
        return $this->api->call(new SuspendUser($user));
    }

    public function unsuspend(string $user): DirectAdminCommand
    {
        return $this->api->call(new UnsuspendUser($user));
    }
}
