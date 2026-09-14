<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('experiment', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();

            $table->timestamps();
        });

        Schema::create('experiment_products', function (Blueprint $table) {
            $table->unsignedBigInteger('experiment_id');
            $table->foreign('experiment_id')->references('id')->on('experiment');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('experiment_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('experiment_id');
            $table->foreign('experiment_id')->references('id')->on('experiment');
            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
        });
    }
};
