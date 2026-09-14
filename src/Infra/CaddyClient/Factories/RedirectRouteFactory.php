<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Factories;

use Symfony\Component\HttpFoundation\Response;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteHandle;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteMatch;
use Waterfront\Infra\CaddyClient\Enums\RedirectType;

class RedirectRouteFactory
{
    public function __construct(
        private readonly RedirectFrameHtmlFactory $redirectFrameHtmlFactory,
    ) {
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    public function make(
        string $routeId,
        string $fromHost,
        string $toUrl,
        RedirectType $redirectType,
        ?array $paths = null,
        ?array $query = null,
    ): RedirectRoute {
        return new RedirectRoute(
            id: $routeId,
            match: [
                new RedirectRouteMatch(
                    host: [$fromHost],
                    path: $paths,
                    query: $query,
                ),
            ],
            handle: [
                $this->makeHandle(
                    redirectType: $redirectType,
                    toUrl: $toUrl,
                ),
            ],
            terminal: true,
        );
    }

    private function makeHandle(
        RedirectType $redirectType,
        string $toUrl,
    ): RedirectRouteHandle {
        return match ($redirectType) {
            RedirectType::FRAME => $this->makeFrameHandle(
                statusCode: $this->resolveStatusCode($redirectType),
                toUrl: $toUrl,
            ),
            RedirectType::MOVED_PERMANENTLY,
            RedirectType::FOUND,
            RedirectType::SEE_OTHER,
            RedirectType::TEMPORARY_REDIRECT,
            RedirectType::PERMANENT_REDIRECT,
                => $this->makeRedirectHandle(
                statusCode: $this->resolveStatusCode($redirectType),
                toUrl: $toUrl,
            ),
        };
    }

    private function makeFrameHandle(
        int $statusCode,
        string $toUrl,
    ): RedirectRouteHandle {
        return new RedirectRouteHandle(
            handler: 'static_response',
            statusCode: $statusCode,
            headers: [
                'Content-Type' => ['text/html; charset=utf-8'],
            ],
            body: $this->redirectFrameHtmlFactory->make($toUrl),
        );
    }

    private function makeRedirectHandle(
        int $statusCode,
        string $toUrl,
    ): RedirectRouteHandle {
        return new RedirectRouteHandle(
            handler: 'static_response',
            statusCode: $statusCode,
            headers: [
                'Location' => [$toUrl],
            ],
        );
    }

    private function resolveStatusCode(RedirectType $redirectType): int
    {
        return match ($redirectType) {
            RedirectType::MOVED_PERMANENTLY => Response::HTTP_MOVED_PERMANENTLY,
            RedirectType::FOUND => Response::HTTP_FOUND,
            RedirectType::SEE_OTHER => Response::HTTP_SEE_OTHER,
            RedirectType::TEMPORARY_REDIRECT => Response::HTTP_TEMPORARY_REDIRECT,
            RedirectType::PERMANENT_REDIRECT => Response::HTTP_PERMANENTLY_REDIRECT,
            RedirectType::FRAME => Response::HTTP_OK,
        };
    }
}
