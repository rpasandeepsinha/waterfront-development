<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Support\Enums\LoggingContextKeys;

class JwtAuthentication
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly LoggerInterface $logger,
        private readonly string $redirectUrl,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->authManager->handleRequest($request);
        } catch (AuthenticationException $exception) {
            $this->logger->error('Authentication failed: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            throw new AuthenticationException('Authentication failed');
        } catch (LogicException) {
            return $this->redirectOrReturnForbidden($request);
        }

        return $next($request);
    }

    private function redirectOrReturnForbidden(Request $request): Response
    {
        if ($request->headers->contains('accept', 'application/json')) {
            return new Response('Forbidden', 403);
        }

        return new RedirectResponse($this->redirectUrl);
    }
}
