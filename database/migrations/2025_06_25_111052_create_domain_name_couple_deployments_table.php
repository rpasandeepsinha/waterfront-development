<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('domain_name_couple_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->bigInteger('origin_provisioning_request_id');

            $table->foreign('origin_provisioning_request_id')
                ->references('id')
                ->on('provisioning_requests')
                ->nullOnDelete();

            $table->string('domain');
            $table->string('couple_type');
            $table->uuid('deployment_uuid');

            $table->index(['domain', 'couple_type', 'deployment_uuid'], 'index_domain_type_deployment');
            $table->unique(['origin_provisioning_request_id', 'couple_type', 'deployment_uuid'], 'unique_request_type_deployment');

            $table->timestamps();
        });
    }
};
