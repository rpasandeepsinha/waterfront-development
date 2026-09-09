<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE product_prices DROP CONSTRAINT unique_discounts');
        DB::statement('ALTER TABLE product_prices ADD CONSTRAINT unique_discounts UNIQUE (product_id, product_discount_id, billing_period, contract_period, type)');
    }
};
