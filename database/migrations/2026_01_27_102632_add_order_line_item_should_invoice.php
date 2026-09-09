<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->boolean('should_invoice')->nullable(false)->default(true);
        });

        Schema::table('order_line_items', function (Blueprint $table) {
            $table->boolean('should_invoice')->nullable(false)->default(null)->change();
        });
    }
};
