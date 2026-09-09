<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminJsonClient;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Waterfront\Infra\DirectAdminJsonClient\Connectors\DirectAdminConnector;
use Waterfront\Infra\DirectAdminJsonClient\DTO\CreateLoginUrlResponse;
use Waterfront\Infra\DirectAdminJsonClient\DTO\DirectAdminServer;
use Waterfront\Infra\DirectAdminJsonClient\Requests\CreateLoginUrl;
use Waterfront\Infra\DirectAdminJsonClient\Serializers\DirectAdminSerializer;

class DirectAdminClient
{
    public function __construct(
        private readonly DirectAdminConnector $connector,
        private readonly DirectAdminSerializer $serializer,
    ) {
    }

    /**
     * @throws RequestException
     * @throws FatalRequestException
     * @throws UnauthorizedException
     * @throws ForbiddenException
     */
    public function createLoginUrl(DirectAdminServer $server): string
    {
        $request = new CreateLoginUrl($server->password);

        $response = $this->connector->sendWithServer($server, $request);

        /** @var CreateLoginUrlResponse $loginUrlResult */
        $loginUrlResult = $this->serializer->deserialize($response->body(), CreateLoginUrlResponse::class, 'json');

        return $loginUrlResult->url;
    }
}
