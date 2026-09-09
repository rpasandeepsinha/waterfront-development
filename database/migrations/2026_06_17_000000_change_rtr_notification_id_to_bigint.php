<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('rtr_response_log', function (Blueprint $table): void {
            $table->bigInteger('rtr_notification_id')->nullable()->change();
            $table->index(
                ['rtr_notification_id', 'source'],
                'rtr_response_log_notification_id_source_index',
            );
        });
    }
};
