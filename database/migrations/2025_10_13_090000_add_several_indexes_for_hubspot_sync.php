<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('updated_at', 'customers_updated_at_index');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('updated_at', 'subscriptions_updated_at_index');
        });
        Schema::table('hubspot_object_sync', function (Blueprint $table) {
            $table->index('updated_at', 'hubspot_object_sync_synced_at_at_index');
        });
        Schema::table('one_time_services', function (Blueprint $table) {
            $table->unique('uuid');
            $table->index('updated_at', 'one_time_services_updated_at_index');
            $table->index('customer_id', 'one_time_services_customer_id_at_index');
            $table->index('subscription_id', 'one_time_services_subscription_id_at_index');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
        });
    }
};
