<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::drop('subscription_prices');
        Schema::drop('order_line_item_prices');
        Schema::drop('custom_price_reasons');

        Schema::create('subscription_price_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->string('type');
            $table->integer('percentage_discount')->nullable();
            $table->integer('fixed_discount')->nullable();
            $table->integer('fixed_price')->nullable();
            $table->integer('order_applied');
            $table->integer('version');
            $table->timestamps();

            $table->unique(['subscription_id', 'type', 'version']);
            $table->unique(['subscription_id', 'order_applied', 'version']);
        });

        DB::statement(<<<SQL
        ALTER TABLE subscription_price_components
          ADD CONSTRAINT price_filled_in_check
          CHECK (num_nonnulls(percentage_discount,fixed_discount,fixed_price) >= 1);
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE subscription_price_components
          ADD CONSTRAINT fixed_discount_non_negative_check
          CHECK (fixed_discount >= 0);
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE subscription_price_components
          ADD CONSTRAINT fixed_price_non_negative_check
          CHECK (fixed_price >= 0);
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE subscription_price_components
          ADD CONSTRAINT percentage_discount_non_negative_check
          CHECK (percentage_discount >= 0 AND percentage_discount <= 100);
        SQL);

        Schema::create('order_line_price_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_item_id')->constrained('order_line_items');
            $table->string('type');
            $table->integer('percentage_discount')->nullable();
            $table->integer('fixed_discount')->nullable();
            $table->integer('fixed_price')->nullable();
            $table->integer('order_applied');
            $table->timestamps();

            $table->unique(['order_line_item_id', 'type']);
            $table->unique(['order_line_item_id', 'order_applied']);
        });

        DB::statement(<<<SQL
        ALTER TABLE order_line_price_components
          ADD CONSTRAINT price_filled_in_check
          CHECK (num_nonnulls(percentage_discount,fixed_discount,fixed_price) >= 1);
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE order_line_price_components
          ADD CONSTRAINT fixed_discount_non_negative_check
          CHECK (fixed_discount >= 0);
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE order_line_price_components
          ADD CONSTRAINT fixed_price_non_negative_check
          CHECK (fixed_price >= 0);
        SQL);

        DB::statement(<<<SQL
        ALTER TABLE order_line_price_components
          ADD CONSTRAINT percentage_discount_non_negative_check
          CHECK (percentage_discount >= 0 AND percentage_discount <= 100);
        SQL);

        Schema::create('custom_price_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_price_component_id')->constrained('subscription_price_components');
            $table->string('reason');
            $table->timestamps();
        });

        DB::table('prices')->whereIn('type', ['pro-rate', 'product-group', 'voucher', 'custom-one-off'])->delete();
    }
};
