<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('opensrs_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_business_unit_id')
                ->constrained('domain_provider_business_unit')
                ->cascadeOnDelete();
            $table->string('api_url');
            $table->string('username');
            // Stored encrypted by the model cast, so the column must hold the ciphertext length.
            $table->text('api_key');
            $table->timestamps();
        });
    }
};
