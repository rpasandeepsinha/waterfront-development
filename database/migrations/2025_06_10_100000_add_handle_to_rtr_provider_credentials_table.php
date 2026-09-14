<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('rtr_provider_credentials', function (Blueprint $table) {
            // Set default for existing rtr credentials (has to be manually updated in Nova after migration has run)
            $table->string('handle')->default('yourhostingsw');
        });

        Schema::table('rtr_provider_credentials', function (Blueprint $table) {
            // Does not set default to null, but removes default (column is not nullable)
            $table->string('handle')->default(null)->change();
        });
    }
};
