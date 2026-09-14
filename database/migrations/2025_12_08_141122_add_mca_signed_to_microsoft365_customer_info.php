<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('microsoft365_customer_info', function (Blueprint $table) {
            $table->dateTime('mca_signed_at')->nullable()->default('2025-12-01');
        });

        Schema::table('microsoft365_customer_info', function (Blueprint $table) {
            $table->dateTime('mca_signed_at')->nullable()->default(null)->change();
        });
    }
};
