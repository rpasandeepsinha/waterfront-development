<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Infra\Authentication\AuthenticationManager;

class RequireWaterfrontRelation
{
    private const string WF_RELATION = 'waterfront';

    public function __construct(
        private readonly AuthenticationManager $authManager,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $authenticatedSubject = $this->authManager->getAuthenticatedSubject();
        if ($authenticatedSubject->identitySchema->schemaId !== SchemaId::CUSTOMER) {
            return $next($request);
        }

        $businessRelations = $authenticatedSubject->identitySchema->metadataPublic->businessRelations ?? null;

        if (is_null($businessRelations) || ! in_array(self::WF_RELATION, $businessRelations, true)) {
            throw new AuthorizationException();
        }

        return $next($request);
    }
}
