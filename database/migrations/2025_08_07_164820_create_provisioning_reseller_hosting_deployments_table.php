<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('provisioning_reseller_hosting_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('origin_provisioning_request_id')->constrained('provisioning_requests')->nullOnDelete();
            $table->foreignId('server_id')->constrained('hosting_servers')->nullOnDelete();
            $table->string('domain')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_hosting_deployments_directadmin', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table
                ->foreignId('reseller_hosting_deployment_id')
                ->constrained('provisioning_reseller_hosting_deployments')
                ->cascadeOnDelete();
            $table->string('username');
            $table->string('domain');
            $table->timestamps();
        });

        Schema::create('reseller_hosting_deployments_plesk', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table
                ->foreignId('reseller_hosting_deployment_id')
                ->constrained('provisioning_reseller_hosting_deployments')
                ->cascadeOnDelete();
            $table->string('subscription_domain');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_reseller_hosting_deployments_plesk');
        Schema::dropIfExists('provisioning_reseller_hosting_deployments_directadmin');
        Schema::dropIfExists('provisioning_reseller_hosting_deployments');
    }
};
