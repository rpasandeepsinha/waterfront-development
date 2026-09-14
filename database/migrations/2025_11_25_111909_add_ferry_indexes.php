<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('customer_migrated_customer', function (Blueprint $table) {
            $table->unique(['customer_id', 'migrated_customer_id']);
        });

        Schema::table('migrated_customers', function (Blueprint $table) {
            $table->index(['group_type', 'reference_customer_number']);
        });

        Schema::table('migrated_subscription_subscription', function (Blueprint $table) {
            $table->unique(['subscription_id', 'migrated_subscription_id']);
        });

        Schema::table('migrated_subscriptions', function (Blueprint $table) {
            $table->index('reference_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('migrated_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['reference_subscription_id']);
        });

        Schema::table('migrated_subscription_subscription', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'migrated_subscription_id']);
        });

        Schema::table('migrated_customers', function (Blueprint $table) {
            $table->dropIndex(['group_type', 'reference_customer_number']);
        });

        Schema::table('customer_migrated_customer', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'migrated_customer_id']);
        });
    }
};
