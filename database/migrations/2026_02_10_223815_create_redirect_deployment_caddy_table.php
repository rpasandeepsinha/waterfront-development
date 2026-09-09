<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('redirect_deployments_caddy', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('redirect_deployment_id')
                ->constrained('redirect_deployments');
            $table->string('server');
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
