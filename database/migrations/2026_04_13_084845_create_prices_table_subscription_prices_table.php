<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate();
            $table->string('type');
            $table->integer('contract_period');
            $table->integer('billing_period');
            $table->integer('price');
            $table->boolean('orderable');
            $table->timestamps();
            $table->index(['product_id', 'type', 'contract_period', 'billing_period']);
        });

        DB::statement(<<<SQL
        ALTER TABLE prices
          ADD CONSTRAINT prices_positive_int_check
          CHECK (price >= 0);
        SQL);

        Schema::create('subscription_prices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->foreignId('price_id')->constrained('prices');
            $table->integer('applied_order')->nullable();
        });
    }
};
