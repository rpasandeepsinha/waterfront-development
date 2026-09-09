<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('order_line_price_components', function (Blueprint $table) {
            $table->integer('version')->nullable(true);
        });

        DB::statement(<<<SQL
        update order_line_price_components set version = 0 where version is null;
        SQL);

        Schema::table('order_line_price_components', function (Blueprint $table) {
            $table->integer('version')->nullable(false)->change();

            $table->dropUnique('order_line_price_components_order_line_item_id_type_unique');
            $table->dropUnique('order_line_price_components_order_line_item_id_order_applied_un');

            $table->unique(['order_line_item_id', 'type', 'version']);
            $table->unique(['order_line_item_id', 'order_applied', 'version']);
        });

        DB::statement(<<<SQL
        ALTER TABLE order_line_price_components
          ADD CONSTRAINT version_non_negative_check
          CHECK (version >= 0);
        SQL);
    }
};
