<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Waterfront\Apps\OneOffScripts\OneOffScript;

class OneOffScriptSeeder extends Seeder
{
    public function run(): void
    {
        $script = new OneOffScript();
        $script->slug = 'add-missing-dns-deployments';
        $script->ticket_ref = 'WATER-6342';
        $script->last_executed_at = CarbonImmutable::now()->subDays(4);
        $script->save();
    }
}
