<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Fakers;

use GuzzleHttp\Client;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\CreateLoginKey;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Package;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Reseller;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\ResellerPackage;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;

class DirectAdmin implements BehavesAsDirectAdmin
{
    private readonly DirectAdminApi $api;

    private readonly User $user;

    private readonly Package $package;

    private readonly Reseller $reseller;

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
        return $this->user;
    }

    /**
     * Wrapper for DirectAdmin reseller methods.
     */
    public function reseller(?DirectAdminServer $server = null): Reseller
    {
        return $this->reseller;
    }

    /**
     * Wrapper for DirectAdmin reseller package methods.
     */
    public function resellerPackage(?DirectAdminServer $server = null): ResellerPackage
    {
        return $this->resellerPackage;
    }

    /**
     * Wrapper for DirectAdmin package methods.
     */
    public function package(?DirectAdminServer $server = null): Package
    {
        return $this->package;
    }

    public function sslCerificate(DirectAdminCommand $sslCommand, string $username, ?DirectAdminServer $server = null): DirectAdminCommand
    {
        if ($server !== null) {
            $this->api->useServer($server);
        }

        return $this->api->loginAs($username)->call($sslCommand);
    }

    /**
     * Use server facade.
     */
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

        $loginKeyCmd->responseReceived([
            'error' => '0',
            'text' => 'One-Time Login URL Created',
            'details' => 'http://test.com',
        ]);

        return $this->api->call($loginKeyCmd)->getLoginUrl();
    }
}
