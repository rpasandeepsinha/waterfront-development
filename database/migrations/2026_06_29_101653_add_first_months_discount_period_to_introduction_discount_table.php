<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('product_introduction_discounts', function (Blueprint $table) {
            $table->integer('first_months_discount_period')->nullable();
        });

        DB::statement(<<<SQL
        ALTER TABLE product_introduction_discounts
          ADD CONSTRAINT first_months_discount_period_check
          CHECK (first_months_discount_period >= 0 AND first_months_discount_period <= contract_period);
        SQL);
    }
};
