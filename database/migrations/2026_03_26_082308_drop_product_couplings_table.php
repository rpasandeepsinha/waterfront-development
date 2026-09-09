<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('product_coupling');

        DB::table('product_prices')->where('type', '=', 'conditional')->delete();
    }
};
