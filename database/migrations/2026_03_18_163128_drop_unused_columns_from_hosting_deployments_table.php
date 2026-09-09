<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hosting_deployments', function (Blueprint $table) {
            $table->dropColumn('memory');
            $table->dropColumn('disk_space');
            $table->dropColumn('email_addresses');
            $table->dropColumn('traffic');
            $table->dropColumn('databases');
            $table->dropColumn('domains');
            $table->dropColumn('permissions');
            $table->dropColumn('ftps_host');
        });
    }
};
