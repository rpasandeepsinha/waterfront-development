<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('redirect_deployments', function (Blueprint $table) {
            $table->string('source');
            $table->string('destination');
            $table->string('type');
        });
    }
};
