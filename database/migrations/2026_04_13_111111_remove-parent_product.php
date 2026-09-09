<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $dropTable = <<<SQL
drop table if exists "parent_product";
SQL;

        DB::statement($dropTable);
    }
};
