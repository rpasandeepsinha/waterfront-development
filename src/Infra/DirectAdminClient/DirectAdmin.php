<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient;

use GuzzleHttp\Client;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\CreateLoginKey;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Package;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Reseller;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\ResellerPackage;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User;

class DirectAdmin implements BehavesAsDirectAdmin
{
    private readonly DirectAdminApi $api;

    private readonly User $user;

    private readonly Reseller $reseller;

    private readonly Package $package;

    private readonly ResellerPackage $resellerPackage;

    public function __construct(DirectAdminServer $server, ?Client $client = null)
    {
        $this->api = new DirectAdminApi($server, $client);
        $this->user = new User($this->api);
        $this->reseller = new Reseller($this->api);
        $this->package = new Package($this->api);
        $this->resellerPackage = new ResellerPackage($this->api);
    }

    /**
     * Wrapper for DirectAdmin user methods.
     */
    public function user(?DirectAdminServer $server = null): User
    {
        if ($server !== null) {
            $this->api->useServer($server);
        }

        return $this->user;
    }

    /**
     * Wrapper for DirectAdmin reseller methods.
     */
    public function reseller(?DirectAdminServer $server = null): Reseller
    {
        if ($server !== null) {
            $this->api->useServer($server);
        }

        return $this->reseller;
    }

    /**
     * Wrapper for DirectAdmin package methods.
     */
    public function package(?DirectAdminServer $server = null): Package
    {
        if ($server !== null) {
            $this->api->useServer($server);
        }

        return $this->package;
    }

    /**
     * Wrapper for DirectAdmin reseller package methods.
     */
    public function resellerPackage(?DirectAdminServer $server = null): ResellerPackage
    {
        if ($server !== null) {
            $this->api->useServer($server);
        }

        return $this->resellerPackage;
    }

    public function sslCerificate(
        DirectAdminCommand $sslCommand,
        string $username,
        ?DirectAdminServer $server = null,
    ): DirectAdminCommand {
        if ($server !== null) {
            $this->api->useServer($server);
        }

        return $this->api->loginAs($username)->call($sslCommand);
    }

    public function useServer(DirectAdminServer $server): DirectAdminApiInterface
    {
        return $this->api->useServer($server);
    }

    /**
     * Get a Single Sign On URL for the given user.
     *
     * @return string URL with hash to use as a one time login
     */
    public function getSSO(string $username = ''): string
    {
        if ($username !== '') {
            $this->api->loginAs($username);
        }

        $currentPassword = (string) $this->api->getConnection()->getServer()->getPassword();

        $loginKeyCmd = new CreateLoginKey()
            ->setOneTimeLogin(true)
            ->setCurrentPassword($currentPassword);

        return $this->api->call($loginKeyCmd)->getLoginUrl();
    }
}
