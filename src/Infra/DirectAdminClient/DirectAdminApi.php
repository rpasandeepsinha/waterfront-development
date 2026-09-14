<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Softaculous;
use Waterfront\Infra\DirectAdminClient\Connection\Connection;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminResponseException;

class DirectAdminApi implements DirectAdminApiInterface
{
    private Connection $connection;

    public function __construct(DirectAdminServer $server, ?Client $client = null)
    {
        $this->connection = new Connection($server, $client);
    }

    /**
     * Make a call to Direct Admin over the connection.
     *
     *
     * @template T of DirectAdminCommand
     *
     * @param DirectAdminCommand&T $command
     *
     * @throws DirectAdminCommandException|Exception|GuzzleException
     *
     * @return DirectAdminCommand&T
     */
    public function call(DirectAdminCommand $command): DirectAdminCommand
    {
        $client = $this->connection->getClient();

        try {
            $options = [];
            if ($command->getMethod() === 'POST') {
                /*
                 * Certain API calls to DA no longer work if this header is not set.
                 *
                 * For some reason when we try to get a list of pop (email) accounts or try to create
                 * a new email account, then the DA API returns the following error (if this header is not set):
                 * Failed [ListPopDomain]: Could not excute your request - You do not own that domain
                 */
                $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            }

            $response = $client->send($command->getRequest(), $options);
            if (! $this->validResponse($response)) {
                if ($command instanceof Softaculous) {
                    // Since Softaculous commands are fire and forget we enforce success.
                    $command->setSucceeded(true);

                    return $command;
                }

                throw new DirectAdminResponseException(
                    'This is not a valid DirectAdmin Server response:' . $response->getBody(),
                );
            }
        } catch (Exception $e) {
            throw new DirectAdminConnectionException(
                '[Could not connect to DirectAdminServer using ' . $this->connection . '] ' . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        } finally {
            $this->connection->resetUser();
        }

        return $command->parseResponse($response);
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
        // No longer check on Server headers as this is risky with OS upgrades and
        // Infra changes.
        return true;
    }

    /**
     * Use a different server to call API commands on.
     * This will switch to a new connection from the given interface.
     *
     * @return DirectAdminApi
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
}
