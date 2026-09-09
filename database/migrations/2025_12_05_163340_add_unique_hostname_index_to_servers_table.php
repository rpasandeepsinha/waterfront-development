<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hosting_servers', function (Blueprint $table) {
            $table->unique(['hostname', 'deleted_at'], 'hosting_servers_hostname_deleted_at_unique');
        });
    }
};
