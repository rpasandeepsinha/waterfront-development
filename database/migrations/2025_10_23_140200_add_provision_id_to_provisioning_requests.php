<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('provisioning_requests', function (Blueprint $table) {
            $table->string('provision_provider')->nullable();
        });

        $updateProvisionProviders = <<<SQL
UPDATE provisioning_requests SET provision_provider = :provision_provider WHERE request_type = 'microsoft365';
SQL;

        DB::statement($updateProvisionProviders, ['provision_provider' => 'microsoft_graph']);

        Schema::table('provisioning_requests', function (Blueprint $table) {
            $table->string('provision_provider')->nullable(false)->change();
        });
    }
};
