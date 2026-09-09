<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('price_version')->default(0);
        });

        DB::statement(<<<SQL
        ALTER TABLE subscriptions
          ADD CONSTRAINT price_version_non_negative_check
          CHECK (price_version >= 0);
        SQL);
    }
};
