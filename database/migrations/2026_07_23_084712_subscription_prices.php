<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('custom_price_reasons')->delete();
        DB::table('subscription_price_components')->delete();
        DB::table('order_line_price_components')->delete();

        Schema::create('subscription_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->references('id')->on('subscriptions');
            $table->integer('net_price');
            $table->timestamp('valid_from');
            $table->timestamps();
        });

        DB::statement(<<<SQL
        ALTER TABLE subscription_prices
          ADD CONSTRAINT net_price_non_negative_check
          CHECK (net_price >= 0);
        SQL);

        Schema::create('order_line_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_line_item_id')->references('id')->on('order_line_items');
            $table->integer('net_price');
            $table->timestamp('valid_from');
            $table->timestamps();
        });

        DB::statement(<<<SQL
        ALTER TABLE order_line_prices
          ADD CONSTRAINT net_price_non_negative_check
          CHECK (net_price >= 0);
        SQL);

        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT price_version_non_negative_check');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->removeColumn('price_version');
            $table->foreignId('subscription_price_id')->nullable()->references('id')->on('subscription_prices');
        });

        DB::statement('ALTER TABLE subscription_price_components DROP COLUMN version');

        Schema::table('subscription_price_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');

            $table->foreignId('subscription_price_id')->references('id')->on('subscription_prices')->onDelete('cascade');

            $table->unique(['subscription_price_id', 'order_applied']);
            $table->unique(['subscription_price_id', 'type']);
        });

        DB::statement('ALTER TABLE order_line_price_components DROP CONSTRAINT version_non_negative_check, DROP COLUMN version');

        Schema::table('order_line_price_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_line_item_id');

            $table->foreignId('order_line_price_id')->references('id')->on('order_line_prices');

            $table->unique(['order_line_price_id', 'order_applied']);
            $table->unique(['order_line_price_id', 'type']);
        });

        Schema::table('custom_price_reasons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_price_component_id');

            $table->foreignId('subscription_price_id')->references('id')->on('subscription_prices')->onDelete('cascade');
        });

        // For existing subscriptions it's impossible to figure out which components led to it's current price. We
        // still want every subscription to have some form of reason for how its price came to be, so we mark every
        // subscription price as 'legacy_backfill' and give it a 'custom-one-off' component, filled with the
        // subscription net_price.

        $query = <<<SQL
        with insert_subscription_prices as (
        	insert into subscription_prices (subscription_id,net_price,valid_from,created_at,updated_at)
        	select
        		subscriptions.id,
        		subscriptions.net_price,
        		now(),
        		now(),
        		now()
        	from subscriptions
        	returning id, subscription_id
        ), link_subscriptions as (
        	update subscriptions
        	set subscription_price_id = insert_subscription_prices.id
        	from insert_subscription_prices
        	where insert_subscription_prices.subscription_id = subscriptions.id
        ), insert_subscription_price_components as (
        	insert into subscription_price_components ("type",percentage_discount,fixed_discount,fixed_price,order_applied,created_at,updated_at,new_price, subscription_price_id)
        	select
        		'custom-one-off',
        		null,
        		null,
        		subscriptions.net_price,
        		1,
        		now(),
        		now(),
        		subscriptions.net_price,
        		insert_subscription_prices.id
        	from insert_subscription_prices
        	join subscriptions on subscriptions.id = insert_subscription_prices.subscription_id
        )
        insert into custom_price_reasons (reason,created_at,updated_at,subscription_price_id)
        select
        	'legacy-backfill',
        	now(),
        	now(),
        	insert_subscription_prices.id
        from insert_subscription_prices
        SQL;

        DB::statement($query);
    }
};
