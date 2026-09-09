<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_addon_coupling', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->bigInteger('parent_product_id');
            $table->bigInteger('addon_product_id');
            $table->foreign('parent_product_id')->references('id')->on('products');
            $table->foreign('addon_product_id')->references('id')->on('products');
            $table->unique(['parent_product_id', 'addon_product_id']);
            $table->timestamps();
        });
    }
};
