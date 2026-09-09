<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN terms_of_payment DROP DEFAULT, ALTER COLUMN terms_of_payment TYPE integer USING terms_of_payment::integer, ALTER COLUMN terms_of_payment SET DEFAULT 14');

        DB::statement('ALTER TABLE hosting_servers ALTER COLUMN port TYPE integer USING port::integer');
    }
};
