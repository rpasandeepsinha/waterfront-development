<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions\DirectAdmin;

use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;
use Waterfront\Infra\DirectAdminJsonClient\DTO\DirectAdminServer as DirectAdminServerDTO;

class DirectAdminGetSsoUrlAction
{
    public function __construct(
        private readonly DirectAdminClient $directAdmin,
    ) {
    }

    /**
     * @throws FatalRequestException
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws RequestException
     */
    public function execute(DirectAdminServer $server, string $username): string
    {
        $password = $server->getPassword() ?? $server->getLoginKey();

        if ($password === null) {
            throw new RuntimeException(
                sprintf(
                    'Trying to generate SSO url from Server without password or login key, Server [%s] ',
                    $server->getDomain(),
                ),
            );
        }

        $serverDTO = new DirectAdminServerDTO(
            baseUrl: $server->getDomain(),
            username: $server->getUsername(),
            password: $password,
            port: $server->getPort(),
            asUser: $username,
        );

        return $this->directAdmin->createLoginUrl($serverDTO);
    }
}
