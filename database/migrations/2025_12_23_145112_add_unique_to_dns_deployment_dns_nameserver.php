<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dns_deployment_dns_nameserver', function (Blueprint $table) {
            $table->unique(
                ['dns_nameserver_id', 'dns_deployment_id'],
                'dns_nameserver_deployment_unique'
            );
        });
    }
};
