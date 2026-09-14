<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('acronis_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('endpoint');
            $table->uuid('tenant_uuid');
            $table->uuid('client_id');
            $table->text('client_secret');
            $table->timestamps();
        });
    }
};
