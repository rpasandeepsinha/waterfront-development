<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient;

use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Infra\CaddyClient\Connectors\CaddyConnector;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\Enums\RedirectType;
use Waterfront\Infra\CaddyClient\Exceptions\CaddySerializerException;
use Waterfront\Infra\CaddyClient\Factories\RedirectRouteFactory;
use Waterfront\Infra\CaddyClient\Generators\CaddyRouteIdGenerator;
use Waterfront\Infra\CaddyClient\Requests\CreateRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Requests\DeleteRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Requests\GetRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Requests\UpdateRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Serializers\CaddySerializer;

class CaddyClient
{
    private const string REDIRECTS_SCOPE_ID = 'redirect';

    public function __construct(
        private readonly CaddyConnector $connector,
        private readonly RedirectRouteFactory $redirectRouteFactory,
        private readonly CaddySerializer $serializer,
        private readonly CaddyRouteIdGenerator $routeIdGenerator,
    ) {
    }

    /**
     * @throws CaddySerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function getRedirect(string $caddyId): RedirectRoute
    {
        $response = $this->connector->send(
            new GetRedirectRouteRequest(
                routeId: $caddyId
            )
        );

        try {
            $redirectRoute = $this->serializer->deserialize($response->body(), RedirectRoute::class, 'json');
        } catch (RuntimeException $exception) {
            throw new CaddySerializerException(RedirectRoute::class, $response->body(), $exception);
        }

        return $redirectRoute;
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     *
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function createRedirect(
        string $fromHost,
        string $toUrl,
        RedirectType $redirectType,
        ?array $paths = null,
        ?array $query = null
    ): string {
        $caddyId = $this->routeIdGenerator->generate(
            fromHost: $fromHost,
            paths: $paths,
            query: $query,
        );

        $payload = $this->redirectRouteFactory->make(
            routeId: $caddyId,
            fromHost: $fromHost,
            toUrl: $toUrl,
            redirectType: $redirectType,
            paths: $paths,
            query: $query,
        );

        $this->connector->send(
            new CreateRedirectRouteRequest(
                payload: $payload,
                redirectsScopeId: self::REDIRECTS_SCOPE_ID,
            )
        );

        return $caddyId;
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     *
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function updateRedirect(
        string $caddyId,
        string $fromHost,
        string $toUrl,
        RedirectType $redirectType,
        ?array $paths = null,
        ?array $query = null,
    ): string {
        $payload = $this->redirectRouteFactory->make(
            routeId: $caddyId,
            fromHost: $fromHost,
            toUrl: $toUrl,
            redirectType: $redirectType,
            paths: $paths,
            query: $query,
        );

        $this->connector->send(
            new UpdateRedirectRouteRequest(
                routeId: $caddyId,
                payload: $payload,
            )
        );

        return $caddyId;
    }

    /**
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function deleteRedirect(string $caddyId): void
    {
        $this->connector->send(
            new DeleteRedirectRouteRequest(
                routeId: $caddyId,
            )
        );
    }
}
