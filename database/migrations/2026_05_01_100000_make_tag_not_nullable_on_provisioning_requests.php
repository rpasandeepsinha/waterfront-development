<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('UPDATE provisioning_requests SET tag = gen_random_uuid()::text WHERE tag IS NULL');

        Schema::table('provisioning_requests', function (Blueprint $table) {
            $table->string('tag')->nullable(false)->change();
        });
    }
};
