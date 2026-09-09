<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hubspot_events', function (Blueprint $table) {
            $table->dropIndex('idx_customer_subscription_latest_updated');
            $table->dropColumn('latest');
        });
    }
};
