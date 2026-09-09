<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cloudstack_managerdomain_deployments', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->nullable();
        });

        $updateCustomerId = <<<SQL
UPDATE cloudstack_managerdomain_deployments SET customer_id = (SELECT customer_id FROM subscriptions WHERE uuid=subscription_uuid);
SQL;

        DB::statement($updateCustomerId);

        Schema::table('cloudstack_managerdomain_deployments', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->nullable(false)->change();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->dropColumn('subscription_uuid');
        });
    }
};
