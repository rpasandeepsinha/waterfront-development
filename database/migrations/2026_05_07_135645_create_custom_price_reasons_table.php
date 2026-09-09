<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('custom_price_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_id')->constrained('prices');
            $table->string('reason');
            $table->timestamps();
        });
    }
};
