<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('rtr_provider_credentials', function (Blueprint $table) {
            $table->text('api_key')->change();
        });

        Schema::table('openprovider_provider_credentials', function (Blueprint $table) {
            $table->text('password')->change();
        });
    }
};
