<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\WpToolkit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\Hosting\WpToolkit\DTO\WpInstanceInfo;
use Waterfront\Domain\Hosting\WpToolkit\DTO\WpLogin;
use Waterfront\Domain\Hosting\WpToolkit\Serializers\WpToolkitSerializerFactory;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\WpToolkitClient\Client;
use Waterfront\Infra\WpToolkitClient\DTO\ConnectionDetails;
use Waterfront\Support\Enums\LoggingContextKeys;

class WpToolkitService
{
    private ?Client $client = null;

    private readonly Serializer $serializer;

    public function __construct(
        private readonly WpToolkitSerializerFactory $serializerFactory,
        private readonly LoggerInterface $logger,
    ) {
        $this->serializer = $this->serializerFactory->get();
    }

    public function instantiateClient(Server $server, ?GuzzleClient $guzzleClient = null): self
    {
        $this->logger->debug(
            'Instantiate WpToolkitClient',
            [
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::SERVER_TYPE => $server->type,
                LoggingContextKeys::META => [
                    'Authentication-type' => $server->secret_key !== ''
                        ? 'Token Authentication'
                        : 'User-Password Authentication',
                ],
            ],
        );

        $this->client = new Client(
            guzzleClient: $guzzleClient ?? new GuzzleClient(),
            connectionDetails: new ConnectionDetails(
                pleskHost: $server->hostname,
                pleskPort: $server->port ?? 8443,
                username: $server->username,
                password: $server->password,
                token: $server->secret_key !== '' ? $server->secret_key : null,
            ),
        );

        return $this;
    }

    public function getWpInstallationId(string $domain): ?int
    {
        $responseJson = $this->client !== null
            ? $this->client->getExistingInstallations()
            : throw new LogicException('Use the method instantiateClient to instantiate a Client connection');

        /** @var WpInstanceInfo[] $receivedInstallations */
        $receivedInstallations = $this->serializer->deserialize($responseJson, WpInstanceInfo::class . '[]', 'json');

        foreach ($receivedInstallations as $instance) {
            if ($instance->domain->name === $domain) {
                return $instance->id;
            }
        }

        return null;
    }

    public function getWpLogin(int $installationId): ?WpLogin
    {
        try {
            $jsonResponse = $this->client !== null
                ? $this->client->getCredentials($installationId)
                : throw new LogicException('Use the method instantiateClient to instantiate a Client connection');

            return $this->serializer->deserialize($jsonResponse, WpLogin::class, 'json');
        } catch (GuzzleException $exception) {
            $this->logger->error(
                'There are no credentials found for installationId',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'WpToolkitInstallationId' => $installationId,
                    ],
                ],
            );
        }

        return null;
    }
}
