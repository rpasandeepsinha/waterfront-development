<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Actions;

use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\History\Models\Audit;

/**
 * This action allows you to create custom Audit logs. If you want a model to log every action e.g. created/updated/deleted
 * event please consider using the 'Auditable' trait on that model. This is meant for custom logging such as creating a
 * suspension subscription timeline. If you need custom audit logs you can also use the 'auditing' and 'audited' observers
 * which are explained here: https://laravel-auditing.com/docs/13.0/audit-events.
 */
class StoreAuditLogAction
{
    /**
     * @param array<string|int, mixed> $oldValues
     * @param array<string|int, mixed> $newValues
     */
    public function execute(
        AuditLogEvent $event,
        string $auditableType,
        int $auditableId,
        array $oldValues = [],
        array $newValues = [],
    ): void {
        $audit = new Audit();
        $audit->event = $event->value;
        $audit->auditable_type = $auditableType;
        $audit->auditable_id = $auditableId;
        $audit->old_values = $oldValues;
        $audit->new_values = $newValues;

        $audit->save();
    }
}
