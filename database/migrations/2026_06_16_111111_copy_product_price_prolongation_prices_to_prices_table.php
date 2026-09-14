<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        $query = <<<SQL
        INSERT INTO prices (product_id, type, contract_period, billing_period, price, orderable, starts_at, created_at, updated_at)
        SELECT product_id, product_prices.type, contract_period, billing_period, regular_price, orderable, product_prices.created_at, product_prices.created_at, product_prices.updated_at
        FROM product_prices
        WHERE type = 'prolongation'
          AND product_discount_id IS NULL
        SQL;
        DB::statement($query);
    }
};
