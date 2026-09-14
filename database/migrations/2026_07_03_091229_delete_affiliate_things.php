<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::drop('affiliate_transactions');
        Schema::drop('affiliate_product_group');
        Schema::drop('affiliate_customer');
        Schema::drop('affiliates');
    }
};
