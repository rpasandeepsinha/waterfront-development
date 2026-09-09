<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Observers;

use Illuminate\Auth\AuthenticationException;
use OwenIt\Auditing\Models\Audit as AuditBaseModel;
use Waterfront\Domain\AuditLogs\Enums\AuditLogUserType;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;

/**
 * This fills in the data when an authenticatedSubject performs an action in WF. If this catches the authentication
 * exception it means the system has performed the action.
 */
class AuditObserver
{
    public function __construct(private readonly AuthenticationManager $authenticationManager)
    {
    }

    public function creating(Audit|AuditBaseModel $audit): void
    {
        try {
            $authenticatedSubject = $this->authenticationManager->getAuthenticatedSubject();

            $auditLogUserType = $authenticatedSubject instanceof AuthenticatedSystem ? AuditLogUserType::CONSOLE : AuditLogUserType::USER;

            $audit['user_type'] = $auditLogUserType;
            $audit['identity_uuid'] = $authenticatedSubject->identitySchema->id;
            $audit['identity_metadata'] = json_encode([
                'email' => $authenticatedSubject->identitySchema->traits?->email,
                'schemaId' => $authenticatedSubject->identitySchema->schemaId->value,
            ], JSON_THROW_ON_ERROR);

            unset($audit['user_id']);
        } catch (AuthenticationException) {
            // @ignoreException
        }
    }
}
