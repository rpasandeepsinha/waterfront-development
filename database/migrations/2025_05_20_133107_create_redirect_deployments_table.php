<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('redirect_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->bigInteger('origin_provisioning_request_id');
            $table->bigInteger('server_id');

            $table->foreign('origin_provisioning_request_id')
                ->references('id')
                ->on('provisioning_requests')
                ->nullOnDelete();

            $table->foreign('server_id')
                ->references('id')
                ->on('hosting_servers')
                ->nullOnDelete();

            $table->string('domain');
            $table->timestamps();
        });
    }
};
