<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('subscription_mutations', function (Blueprint $table) {
            $table->dateTime('process_technical_at')->nullable();
            $table->dateTime('processed_technical_at')->nullable();
        });
    }
};
