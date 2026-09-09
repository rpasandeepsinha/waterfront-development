<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Connection\Connection;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

interface DirectAdminApiInterface
{
    /**
     * Make a call to Direct Admin over the connection.
     *
     * @param DirectAdminCommand&T $command
     *
     * @throws ReflectionException|GuzzleException|Exception|DirectAdminCommandException
     *
     * @return DirectAdminCommand&T
     *
     * @template T of DirectAdminCommand
     *
     */
    public function call(DirectAdminCommand $command): DirectAdminCommand;

    /**
     * Validate the response given by a DirectAdmin server.
     *
     * @param ResponseInterface $response Response to validate for DirectAdmin
     *
     * @return bool true if the response was valid from a DirectAdmin server.
     */
    public function validResponse(ResponseInterface $response): bool;

    /**
     * Use a different server to call API commands on.
     * This will switch to a new connection from the given interface.
     */
    public function useServer(DirectAdminServer $server): DirectAdminApiInterface;

    /**
     * Login as a specific user on the API.
     */
    public function loginAs(string $username): DirectAdminApiInterface;

    /**
     * Get the current connection to the DirectAdminApi.
     */
    public function getConnection(): Connection;
}
