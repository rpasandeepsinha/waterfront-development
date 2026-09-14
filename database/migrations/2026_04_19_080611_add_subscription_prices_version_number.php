<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('subscription_prices', function (Blueprint $table) {
            $table->integer('version');

            $table->unique(['subscription_id', 'price_id', 'version']);
            $table->unique(['subscription_id', 'applied_order', 'version']);
        });
    }
};
