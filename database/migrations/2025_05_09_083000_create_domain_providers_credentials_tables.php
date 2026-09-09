<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('rtr_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')
                ->constrained('providers')
                ->cascadeOnDelete();
            $table->foreignId('domain_business_unit_id')
                ->constrained('domain_provider_business_unit')
                ->cascadeOnDelete();
            $table->string('api_url');
            $table->string('api_key');
            $table->timestamps();
        });

        Schema::create('openprovider_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')
                ->constrained('providers')
                ->cascadeOnDelete();
            $table->foreignId('domain_business_unit_id')
                ->constrained('domain_provider_business_unit')
                ->cascadeOnDelete();
            $table->string('api_url');
            $table->string('username');
            $table->string('password');
            $table->timestamps();
        });
    }
};
