<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cancellation_flows', function (Blueprint $table) {
            $table->uuid('identity_uuid');
            $table->jsonb('identity_metadata');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('sent_hubspot_at')->nullable();
        });

        Schema::create('cancellation_flows_subscriptions', function (Blueprint $table) {
            $table->foreignId('cancellation_flows_id')->references('id')->on('cancellation_flows');
            $table->foreignId('subscription_id')->references('id')->on('subscriptions');
            $table->timestamp('uncancelled_at')->nullable();
        });
    }
};
