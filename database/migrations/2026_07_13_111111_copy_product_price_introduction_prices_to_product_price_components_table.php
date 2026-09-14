<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        $query = <<<SQL
        INSERT INTO product_price_components (product_id, type, contract_period, billing_period, price, orderable, starts_at, created_at, updated_at)
        SELECT product_id, 'introduction', contract_period, billing_period, introduction_price, orderable, product_prices.created_at, product_prices.created_at, product_prices.updated_at
        FROM product_prices
        WHERE introduction_price IS NOT NULL
          AND introduction_price >= 0
          AND type = 'registration'
          AND product_discount_id IS NULL
        SQL;
        DB::statement($query);
    }
};
