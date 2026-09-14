<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index('customer_id', 'customer_contacts_customer_id_index');
        });

        Schema::table('product_price_alternatives', function (Blueprint $table) {
            $table->index('product_price_id', 'product_price_alternatives_product_price_id_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('product_group_id', 'product_group_id_index');
        });
    }
};
