<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('microsoft365_customer_info', function (Blueprint $table) {
            $table->string('primary_domain')->nullable();
            $table->string('primary_domain_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('microsoft365_customer_info', function (Blueprint $table) {
            $table->dropColumn('primary_domain');
            $table->dropColumn('primary_domain_status');
        });
    }
};
