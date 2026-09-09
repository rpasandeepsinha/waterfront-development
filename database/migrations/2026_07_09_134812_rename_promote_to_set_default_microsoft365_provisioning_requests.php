<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $renameSetDefaultRequests = <<<SQL
UPDATE provisioning_requests
SET request_name = 'set_microsoft365_domain_as_default'
WHERE request_name = 'promote_microsoft365_domain'
AND id IN (
    SELECT request_id
    FROM provisioning_results
    WHERE response::text LIKE '%"isDefault"%'
)
SQL;

        DB::statement($renameSetDefaultRequests);
    }
};
