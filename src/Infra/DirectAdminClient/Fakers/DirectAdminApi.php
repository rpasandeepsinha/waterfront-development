<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Fakers;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\GetNameServers;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackagesUser;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackageUser;
use Waterfront\Infra\DirectAdminClient\Commands\Pop\ListPopDomain;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerConfig;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellers;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerUsers;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserConfig;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserStats;
use Waterfront\Infra\DirectAdminClient\Connection\Connection;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;

class DirectAdminApi implements DirectAdminApiInterface
{
    private Connection $connection;

    public function __construct(DirectAdminServer $server, ?Client $client = null)
    {
        $this->connection = new Connection($server, $client);
    }

    /**
     * Make a FAKE call to Direct Admin over the connection.
     *
     * @throws Exception
     */
    public function call(DirectAdminCommand $command): DirectAdminCommand
    {
        // Return The command directly without doing anything.
        $command->setSucceeded(true);

        $json = '';

        if ($command instanceof ShowUserStats) {
            /** @var string $json */
            $json = file_get_contents(__DIR__ . '/data/show_user_stats.json');
            /** @var array<mixed, mixed> $array */
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $command->responseReceived($array);
        }

        if ($command instanceof PackageUser) {
            /** @var string $json */
            $json = file_get_contents(__DIR__ . '/data/package_user.json');
        }

        if ($command instanceof PackagesUser) {
            $json = '["basic","brons","groot"]';
        }

        if ($json !== '') {
            /** @var array<mixed, mixed> $result */
            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $command->responseReceived($result);
        }

        if ($command instanceof ShowUserConfig) {
            $response = require 'data/user_config.php';
            $command->setUserConfig($response);
        }

        if ($command instanceof ListPopDomain) {
            $command->setDomain('mytestdomain.com');
            $command->setUsers(['mytestuser1', 'mytestuser2']);
        }

        if ($command instanceof ShowResellers) {
            $command->setFormValues([
                'list' => [ 'TestResellerUser' ],
            ]);
        }

        if ($command instanceof ShowResellerUsers) {
            $command->setFormValues([
                 'list' => ['customer1', 'customer2'],
            ]);
        }

        if ($command instanceof ShowResellerConfig) {
            $command->setResellerConfig([
                'dnscontrol' => 'OFF',
                'ssh' => 'ON',
                'ssl' => 'ON',
            ]);
        }

        if ($command instanceof GetNameServers) {
            $command->setFormValues([
                'NS1' => 'ns1.axc.nl',
                'NS2' => 'ns2.axc.nl',
            ]);
        }

        return $command;
    }

    /**
     * Use a different server to call API commands on.
     * This will switch to a new connection from the given interface.
     */
    public function useServer(DirectAdminServer $server): DirectAdminApiInterface
    {
        $this->connection = new Connection($server);
        return $this;
    }

    /**
     * Login as a specific user on the API.
     *
     * @return DirectAdminApi
     */
    public function loginAs(string $username): DirectAdminApiInterface
    {
        $this->connection->asUser($username);
        return $this;
    }

    /**
     * Get the current connection to the DirectAdminApi.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Validate the response given by a DirectAdmin server.
     *
     * @param ResponseInterface $response Response to validate for DirectAdmin
     *
     * @return bool true if the response was valid from a DirectAdmin server.
     */
    public function validResponse(ResponseInterface $response): bool
    {
        if (! $response->hasHeader('Server')) {
            return false;
        }

        if (! Str::contains($response->getHeader('Server')[0], 'DirectAdmin')) {
            return false;
        }

        return true;
    }
}
