<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Resolver;

use Illuminate\Contracts\Auth\Authenticatable;
use OwenIt\Auditing\Contracts\UserResolver;

/**
 * We need this resolver so that the audit package thinks the action is performed by the system while the AuditObserver
 * fills in the `identity_uuid` containing the information of who did the audit.
 */
class IdentityResolver implements UserResolver
{
    public static function resolve(): ?Authenticatable
    {
        return null;
    }
}
