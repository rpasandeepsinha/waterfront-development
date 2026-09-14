<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('migrated_dns_internal_nameservers', function (Blueprint $table) {
            $table->id();
            $table->string('nameserver_hostname')->unique();
            $table->timestamps();
        });
    }
};
