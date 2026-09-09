<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('puzzle_callback_requests', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['puzzle_callback_timeslot_id']);
        });

        Schema::rename('puzzle_callback_timeslots', 'puzzel_callback_timeslots');
        Schema::rename('puzzle_callback_requests', 'puzzel_callback_requests');

        Schema::table('puzzel_callback_requests', function (Blueprint $table): void {
            $table->renameColumn('puzzle_callback_timeslot_id', 'puzzel_callback_timeslot_id');
        });

        Schema::table('puzzel_callback_requests', function (Blueprint $table): void {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreign('puzzel_callback_timeslot_id')
                ->references('id')
                ->on('puzzel_callback_timeslots');
        });
    }
};
