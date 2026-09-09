<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_retention_offers', function (Blueprint $table): void {
            $table->foreignId('subscription_mutation_id')
                ->nullable()
                ->constrained('subscription_mutations')
                ->nullOnDelete();
            $table->integer('discount_amount')->nullable();
            $table->integer('credit_amount')->nullable();
            $table->timestamp('effective_at')->nullable();
        });

        DB::table('customer_retention_offers')->update([
            'effective_at' => DB::raw('created_at'),
        ]);

        Schema::table('customer_retention_offers', function (Blueprint $table): void {
            $table->timestamp('effective_at')->nullable(false)->change();
        });
    }
};
