<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('product_experiment_offerings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_1_id')->constrained('products');
            $table->foreignId('product_2_id')->constrained('products');
            $table->boolean('product_1_free')->default(false);
            $table->boolean('product_2_free')->default(false);
            $table->timestamps();
        });

        Schema::create('product_experiment_offerings_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('product_experiment_offering_id')->constrained('product_experiment_offerings');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'product_experiment_offering_id']);
        });
    }
};
