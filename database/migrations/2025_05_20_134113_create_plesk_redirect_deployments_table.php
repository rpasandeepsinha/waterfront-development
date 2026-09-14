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
        Schema::create('plesk_redirect_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->bigInteger('redirect_deployment_id')->unique();

            $table->foreign('redirect_deployment_id')->references('id')->on('redirect_deployments')->cascadeOnDelete();

            $table->string('username');
            $table->integer('plesk_customer_id');
            $table->timestamps();
        });
    }
};
