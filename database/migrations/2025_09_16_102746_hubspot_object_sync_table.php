<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('hubspot_object_sync', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->string('sandwave_object_type');
            $table->uuid('sandwave_object_id');
            $table->string('hubspot_object_id');
            $table->dateTime('synced_at');
            $table->unique(['sandwave_object_type', 'sandwave_object_id']);
            $table->timestamps();
        });
    }
};
