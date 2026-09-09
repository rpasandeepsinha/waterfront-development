<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('order_line_item_prices', function (Blueprint $table) {
            $table->foreignId('order_line_item_id')->constrained('order_line_items');
            $table->foreignId('price_id')->constrained('prices');
            $table->integer('applied_order');
        });
    }
};
