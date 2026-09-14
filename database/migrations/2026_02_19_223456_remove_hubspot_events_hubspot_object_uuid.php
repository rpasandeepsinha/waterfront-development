<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        $deleteRecords = <<<SQL
        delete from hubspot_events where hubspot_object_uuid is not null;
        SQL;

        DB::raw($deleteRecords);

        Schema::table('hubspot_events', function (Blueprint $table) {
            $table->dropColumn('hubspot_object_uuid');
        });
    }
};
