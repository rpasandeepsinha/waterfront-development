<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('puzzle_callback_timeslots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('capacity');
            $table->time('start_timeslot');
            $table->time('end_timeslot');
            $table->timestamps();
        });

        Schema::create('puzzle_callback_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->foreignId('puzzle_callback_timeslot_id')->constrained('puzzle_callback_timeslots');

            $table->string('phone_number', 16);

            $table->string('name');
            $table->string('request_category', 100);
            $table->text('request_description');
            $table->dateTime('desired_callback_time');
            $table->timestamps();
        });
    }
};
