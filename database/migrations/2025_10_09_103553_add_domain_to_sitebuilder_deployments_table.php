<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('sitebuilder_deployments', function (Blueprint $table) {
            $table->string('domain');
        });
    }

    public function down(): void
    {
        Schema::table('sitebuilder_deployments', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }
};
