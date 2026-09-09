<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cloudstack_jobs', function (Blueprint $table) {
            $table->uuid('template_uuid')->nullable();
            $table->uuid('ssh_key_uuid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cloudstack_jobs', function (Blueprint $table) {
            $table->dropColumn(['ssh_key_uuid', 'template_uuid']);
        });
    }
};
