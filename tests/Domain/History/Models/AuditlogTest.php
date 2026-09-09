<?php

declare(strict_types=1);

namespace Tests\Domain\History\Models;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\History\Models\Audit;

#[CoversClass(Audit::class)]
class AuditlogTest extends IntegrationTestCase
{
    #[Test]
    public function auditLogIsSavedWithIdentityInformation(): void
    {
        /*
         * See: https://github.com/owen-it/laravel-auditing/compare/v14.0.4...v14.0.6#diff-682a5b02fc6acf79eda6201541882897f0f0a3fca0d35f02e3398021fd77c63eR80
         * Because of that change we have to enable it in this test, because it gets disabled in IntegrationTestCase::setUp.
         * See: IntegrationTestCase::disableAuditLogging
         */
        Config::set('audit.enabled', true);

        $identity = new CustomerFactory()->createOne(['email' => 'piet@example.org']);
        $this->actingAsCustomer($identity);

        Customer::enableAuditing();

        $customer = new CustomerFactory()->createOne();

        $auditLog = Audit::firstOrFail();

        self::assertSame(Customer::class, $auditLog->auditable_type);
        self::assertSame($customer->id, $auditLog->auditable_id);
        self::assertSame($identity->uuid->toString(), $auditLog->identity_uuid?->toString());
        self::assertSame('{"email": "piet@example.org", "schemaId": "customer"}', $auditLog->identity_metadata ?? '');
    }
}
