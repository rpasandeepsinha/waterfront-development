<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class() extends Migration {
    public function up(): void
    {
        DB::table('product_prices')->where('type', '=', 'one_time_service')->update(['type' => 'registration']);
    }
};
