<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sitebuilder_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('origin_provisioning_request_id')
                ->constrained('provisioning_requests')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sitebuilder_context_basekit', function (Blueprint $table) {
            $table->id();
            $table->uuid('context_uuid')->unique();
            $table->bigInteger('user_ref')->unique();
            $table->timestamps();
        });

        Schema::create('sitebuilder_deployments_basekit', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('sitebuilder_deployment_id')
                ->constrained('sitebuilder_deployments')
                ->nullOnDelete();
            $table->bigInteger('site_ref');
            $table->timestamps();
        });
    }
};
