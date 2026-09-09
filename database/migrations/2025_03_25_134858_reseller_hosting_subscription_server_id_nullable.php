<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('reseller_hosting_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reseller_hosting_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->nullable(false)->change();
        });
    }
};
