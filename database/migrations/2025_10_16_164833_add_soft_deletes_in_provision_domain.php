<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('hosting_deployments_directadmin', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('hosting_deployments_plesk', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('provisioning_hosting_deployments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('plesk_redirect_deployments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('redirect_deployments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('reseller_hosting_deployments_plesk', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('provisioning_reseller_hosting_deployments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('reseller_hosting_deployments_directadmin', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sitebuilder_context_basekit', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sitebuilder_deployments_basekit', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sitebuilder_deployments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('domain_name_couple_deployments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
