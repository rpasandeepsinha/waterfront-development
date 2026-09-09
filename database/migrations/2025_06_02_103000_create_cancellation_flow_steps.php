<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cancellation_flow_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->foreignId('cancellation_flow_id')
                ->constrained('cancellation_flows')
                ->cascadeOnDelete();
            $table->string('type');
            $table->jsonb('step_data');
            $table->jsonb('response_data');
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }
};
