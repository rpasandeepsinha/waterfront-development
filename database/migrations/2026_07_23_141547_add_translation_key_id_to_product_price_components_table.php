<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_price_components', function (Blueprint $table) {
            $table->foreignId('translation_key_id')->nullable()->constrained('translation_keys');
        });

        DB::statement(<<<SQL
        UPDATE product_price_components
        SET translation_key_id = product_prices.translation_key_id
        FROM product_prices
        WHERE product_price_components.product_id = product_prices.product_id
          AND product_price_components.contract_period = product_prices.contract_period
          AND product_price_components.billing_period = product_prices.billing_period
          AND product_price_components.type = 'registration'
          AND product_prices.type = 'registration'
          AND product_prices.translation_key_id IS NOT NULL
        SQL);
    }
};
