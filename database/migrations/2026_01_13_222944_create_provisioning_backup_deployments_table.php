<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('backup_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('origin_provisioning_request_id')
                ->constrained('provisioning_requests');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('backup_deployments_acronis', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('acronis_provider_id')
                ->constrained('acronis_providers');
            $table->foreignId('backup_deployment_id')
                ->constrained('backup_deployments');
            $table->uuid('tenant_uuid');
            $table->uuid('user_uuid');
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
