<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('domain_contact_provider', function (Blueprint $table) {
            $table->foreignId('domain_business_unit_id')
                ->nullable()
                ->references('id')
                ->on('domain_provider_business_unit');
        });
    }
};
