<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('subscription_changes', function (Blueprint $table): void {
            $table->integer('failure_code')->nullable();
            $table->text('failure_message')->nullable();
        });
    }
};
