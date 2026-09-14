<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        $query = <<<SQL
        insert into subscription_categories (subscription_id, name, created_at, updated_at)
        select s.id, 'suspension', now(), now()
        from subscriptions s
        left join subscription_categories c on s.id = c.subscription_id
        where administrative_status in ('suspended', 'canceled', 'expired')
        and technical_status = 'suspension_failed'
        and c.id is null
        SQL;

        DB::statement($query);

        $query = <<<SQL
        insert into subscription_categories (subscription_id, name, created_at, updated_at)
        select s.id, 'unsuspension', now(), now()
        from subscriptions s
        left join subscription_categories c on s.id = c.subscription_id
        where administrative_status != 'suspended'
        and technical_status = 'unsuspension_failed'
        and c.id is null
        SQL;

        DB::statement($query);
    }
};
