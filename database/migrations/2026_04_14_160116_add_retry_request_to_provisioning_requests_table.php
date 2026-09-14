<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('provisioning_requests', function (Blueprint $table) {
            $table
                ->foreignIdFor(ProvisioningRequest::class, 'retry_of_request_id')
                ->nullable()
                ->constrained('provisioning_requests')
                ->nullOnDelete();

            $table->uuid('requested_by_uuid')->nullable();
        });
    }
};
