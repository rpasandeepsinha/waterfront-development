<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('subscription_price_components', function (Blueprint $table) {
            $table->integer('new_price')->nullable();
            $table->float('percentage_discount')->nullable()->change();
        });

        DB::table('subscription_price_components')->update([
            'new_price' => DB::raw('fixed_price'),
        ]);

        Schema::table('subscription_price_components', function (Blueprint $table) {
            $table->integer('new_price')->nullable(false)->change();
        });

        DB::statement(<<<SQL
        ALTER TABLE subscription_price_components
          ADD CONSTRAINT new_price_non_negative_check
          CHECK (new_price >= 0);
        SQL);

        Schema::table('order_line_price_components', function (Blueprint $table) {
            $table->integer('new_price')->nullable();
            $table->float('percentage_discount')->nullable()->change();
        });

        DB::table('order_line_price_components')->update([
            'new_price' => DB::raw('fixed_price'),
        ]);

        Schema::table('order_line_price_components', function (Blueprint $table) {
            $table->integer('new_price')->nullable(false)->change();
        });

        DB::statement(<<<SQL
        ALTER TABLE order_line_price_components
          ADD CONSTRAINT new_price_non_negative_check
          CHECK (new_price >= 0);
        SQL);
    }
};
