<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        /*
         * This pivot table currently has no primary key and no unique. It only has dns_nameserver_id
         * and dns_deployment_id as foreign keys.
         *
         * It can be impossible to determine a specific row when the same nameserver is coupled to
         * the same deployment when both timestamps are null. So id will be added to this table
         * to be able to delete a specific row.
         */
        Schema::table('dns_deployment_dns_nameserver', function (Blueprint $table) {
            $table->id();
        });
    }
};
