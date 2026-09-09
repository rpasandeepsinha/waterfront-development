<?php

declare(strict_types=1);

namespace Tests\Domain\AuditLogs\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\AuditLogs\Enums\AuditLogUserType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\History\Models\Audit;

#[CoversClass(StoreAuditLogAction::class)]
class StoreAuditLogActionTest extends IntegrationTestCase
{
    #[Test]
    public function executeNoUser(): void
    {
        self::resolve(StoreAuditLogAction::class)
            ->execute(
                AuditLogEvent::CREATED,
                Customer::class,
                1,
                ['test' => 'test'],
                ['test' => 'hallo']
            );

        $auditLog = Audit::where('auditable_type', Customer::class)->first();

        self::assertNotNull($auditLog);
        self::assertSame(AuditLogEvent::CREATED->value, AuditLogEvent::from($auditLog->event)->value);
        self::assertNotNull($auditLog->user_type);
        self::assertSame(AuditLogUserType::CONSOLE->value, AuditLogUserType::from($auditLog->user_type)->value);
        self::assertNotNull($auditLog->identity_uuid);
        self::assertNotNull($auditLog->identity_metadata);
    }

    #[Test]
    public function executeWithUser(): void
    {
        $this->actingAsEmployee();

        self::resolve(StoreAuditLogAction::class)
            ->execute(
                AuditLogEvent::CREATED,
                Customer::class,
                1,
                ['test' => 'test'],
                ['test' => 'hallo']
            );

        $auditLog = Audit::where('auditable_type', Customer::class)->first();

        self::assertNotNull($auditLog);
        self::assertSame(AuditLogEvent::CREATED->value, AuditLogEvent::from($auditLog->event)->value);
        self::assertNotNull($auditLog->user_type);
        self::assertSame(AuditLogUserType::USER->value, AuditLogUserType::from($auditLog->user_type)->value);
        self::assertNotNull($auditLog->identity_uuid);
        self::assertNotNull($auditLog->identity_metadata);
    }
}
