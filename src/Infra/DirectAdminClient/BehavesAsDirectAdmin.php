<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Package;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Reseller;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\ResellerPackage;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User;

interface BehavesAsDirectAdmin
{
    /**
     * Wrapper for DirectAdmin user methods.
     */
    public function user(?DirectAdminServer $server = null): User;

    /**
     * Wrapper for DirectAdmin reseller methods.
     */
    public function reseller(?DirectAdminServer $server = null): Reseller;

    /**
     * Wrapper for DirectAdmin package methods.
     */
    public function package(?DirectAdminServer $server = null): Package;

    /**
     * Wrapper for DirectAdmin reseller package methods.
     */
    public function resellerPackage(?DirectAdminServer $server = null): ResellerPackage;

    public function sslCerificate(DirectAdminCommand $sslCommand, string $username, ?DirectAdminServer $server = null): DirectAdminCommand;

    public function useServer(DirectAdminServer $server): DirectAdminApiInterface;

    /**
     * Get a Single Sign On URL for the given user.
     *
     * @return string URL with hash to use as a one time login
     */
    public function getSSO(string $username = ''): string;
}
