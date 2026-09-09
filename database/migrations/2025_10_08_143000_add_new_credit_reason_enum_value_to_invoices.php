<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_credit_reason_check');
        $types = [
            'reason_wet_van_dam',
            'reason_revocation',
            'reason_dissatisfied',
            'reason_cancellation',
            'reason_cancellation_renewal',
            'reason_failure',
            'reason_downgrade',
            'reason_other',
            'reason_retention',
            'reason_abuse',
            'reason_discount',
            'reason_incorrect',
        ];
        $result = join(', ', array_map(fn ($value) => sprintf("'%s'::character varying", $value), $types));
        DB::statement("ALTER TABLE invoices add CONSTRAINT invoices_credit_reason_check CHECK (credit_reason::text = ANY (ARRAY[$result]::text[]))");
    }

    public function down(): void
    {
        DB::statement('UPDATE invoices SET credit_reason = NULL WHERE credit_reason = \'reason_discount\' ;');
        DB::statement('UPDATE invoices SET credit_reason = NULL WHERE credit_reason = \'reason_incorrect\' ;');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_credit_reason_check');
        $types = [
            'reason_wet_van_dam',
            'reason_revocation',
            'reason_dissatisfied',
            'reason_cancellation',
            'reason_cancellation_renewal',
            'reason_failure',
            'reason_downgrade',
            'reason_other',
            'reason_retention',
            'reason_abuse',
        ];
        $result = join(', ', array_map(fn ($value) => sprintf("'%s'::character varying", $value), $types));
        DB::statement("ALTER TABLE invoices add CONSTRAINT invoices_credit_reason_check CHECK (credit_reason::text = ANY (ARRAY[$result]::text[]))");
    }
};
