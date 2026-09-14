<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('product_introduction_discounts', function (Blueprint $table) {
            $table->renameColumn('amount', 'max_uses_per_customer');
        });

        DB::statement(<<<SQL
        ALTER TABLE product_introduction_discounts
          ADD CONSTRAINT max_uses_per_customer_non_negative_check
          CHECK (max_uses_per_customer >= 0);
        SQL);
    }
};
