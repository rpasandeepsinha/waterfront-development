<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;

class RequireAuthenticatedSystem
{
    public function __construct(private readonly AuthenticationManager $authManager, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $context = $this->authManager->getAuthenticatedSubject();

        if (! $context instanceof AuthenticatedSystem) {
            $this->logger->debug(sprintf('Subject is not a system: %s', json_encode($context)));
            throw new AuthorizationException('Subject is not a system.');
        }

        return $next($request);
    }
}
