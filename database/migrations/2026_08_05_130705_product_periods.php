<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('product_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->integer('contract_period');
            $table->integer('billing_period');
            $table->integer('action_period')->nullable();
            $table->integer('action_period_price')->nullable();
            $table->foreignId('translation_key_id')->nullable()->constrained('translation_keys');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'contract_period', 'billing_period']);
        });

        DB::statement(<<<SQL
        ALTER TABLE product_periods
          ADD CONSTRAINT valid_period_check
          CHECK (contract_period >= 1 AND billing_period >= 1 AND contract_period % billing_period = 0);
        SQL);

        DB::statement(<<<SQL
        CREATE UNIQUE INDEX product_periods_default_unique
          ON product_periods (product_id, contract_period, billing_period)
          WHERE is_default;
        SQL);

        DB::statement(<<<SQL
        insert into product_periods (product_id, contract_period, billing_period, action_period, action_period_price, is_default, translation_key_id)
        select
        distinct on (pp.product_id, pp.contract_period, pp.billing_period) pp.product_id, pp.contract_period, pp.billing_period, pp.action_period, pp.action_period_price, pp.is_default, pp.translation_key_id
        from product_prices pp
        where pp.product_discount_id is null and pp.type = 'registration'
        SQL);
    }
};
