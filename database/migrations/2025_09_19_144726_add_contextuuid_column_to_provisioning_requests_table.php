<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('provisioning_requests', function (Blueprint $table) {
            $table->uuid('context_uuid')->default(Uuid::uuid4());
        });

        Schema::table('provisioning_requests', function (Blueprint $table) {
            $table->uuid('context_uuid')->default(null)->change();
        });
    }
};
