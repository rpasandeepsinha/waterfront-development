<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('subscription_categories', function (Blueprint $table) {
            $table->jsonb('assignee_metadata')->nullable();
            $table->string('name')->nullable()->change();
        });
    }
};
