<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_retention_offers', function (Blueprint $table): void {
            $table->id();
            $table->json('created_by_metadata');
            $table->string('customer_type');
            $table->foreignId('subscription_id')
                ->constrained('subscriptions');
            $table->string('selected_action');
            $table->string('puzzel_ticket_id');
            $table->timestamps();
        });
    }
};
