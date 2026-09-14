<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<SQL
        ALTER TABLE product_allowed_changes
          DROP CONSTRAINT IF EXISTS product_allowed_changes_change_type_check;
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE product_allowed_changes
          ADD CONSTRAINT product_allowed_changes_change_type_check
          CHECK (change_type IN ('upgrade','downgrade','reinstall'));
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE product_allowed_changes DROP CONSTRAINT IF EXISTS product_allowed_changes_change_type_check;',
        );
        DB::statement(<<<SQL
        ALTER TABLE product_allowed_changes
        ADD CONSTRAINT product_allowed_changes_change_type_check
        CHECK (change_type IN ('upgrade','downgrade'));
        SQL);
    }
};
