<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('redirects_context_caddy', function (Blueprint $table) {
            $table->id();
            $table->uuid('context_uuid')->unique();
            $table->string('host');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
