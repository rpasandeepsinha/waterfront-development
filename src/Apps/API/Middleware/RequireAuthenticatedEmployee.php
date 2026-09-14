<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use SandwaveIo\LighthouseAuthBase\Enum\AuthenticationMethod;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Support\Enums\Environment;
use Waterfront\Support\Enums\LoggingContextKeys;

class RequireAuthenticatedEmployee
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly LoggerInterface $logger,
        private readonly string $redirectUrl,
        private readonly Environment $environment,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $employee = $this->authManager->getAuthenticatedEmployee();
        } catch (AuthenticationException|AuthorizationException $exception) {
            $this->logger->debug('Checking required employee authentication failed: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return $this->redirectOrReturnForbidden($request);
        }

        if (! $employee->verified) {
            $this->logger->debug('Employee is not verified');

            return $this->redirectOrReturnForbidden($request);
        }

        // Setting up MFA in local development environment is not possible
        if ($this->environment === Environment::DEV) {
            return $next($request);
        }

        $secureAuthenticationEnabled =
            $employee->identitySchema->authenticatedSession->isUsingSecureAuthentication ?? false;
        $oidcAuthenticated = in_array(
            AuthenticationMethod::OIDC,
            $employee->identitySchema->authenticatedSession->availableAuthenticationMethods ?? [],
            true,
        );

        if (! $secureAuthenticationEnabled && ! $oidcAuthenticated) {
            $this->logger->debug('Employee is not using secure authentication');

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
