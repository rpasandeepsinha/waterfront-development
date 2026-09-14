<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('customer_since')->nullable();
        });

        DB::statement('UPDATE customers SET customer_since = DATE(COALESCE(created_at, NOW()))');

        Schema::table('customers', function (Blueprint $table) {
            $table->date('customer_since')->nullable(false)->change();
        });
    }
};
