<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provisioning_hosting_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->bigInteger('origin_provisioning_request_id');
            $table->bigInteger('server_id');

            $table
                ->foreign('origin_provisioning_request_id')
                ->references('id')
                ->on('provisioning_requests')
                ->nullOnDelete();

            $table->foreign('server_id')->references('id')->on('hosting_servers')->nullOnDelete();

            $table->string('domain')->nullable();
            $table->timestamps();
        });

        Schema::create('hosting_deployments_directadmin', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('username');
            $table->string('default_domain')->nullable();
            $table->bigInteger('hosting_deployment_id')->unique();

            $table
                ->foreign('hosting_deployment_id')
                ->references('id')
                ->on('provisioning_hosting_deployments')
                ->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create('hosting_deployments_plesk', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('customer_name');
            $table->string('subscription_domain');
            $table->bigInteger('hosting_deployment_id')->unique();

            $table
                ->foreign('hosting_deployment_id')
                ->references('id')
                ->on('provisioning_hosting_deployments')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }
};
