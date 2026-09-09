<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;

class ManualSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Manual Subscription';
        $group->ledger_code = 8013;
        $group->slug = ProductGroupType::MANUAL_SUBSCRIPTION;
        $group->default_billing_period = 12;
        $group->default_contract_period = 12;
        $group->save();
    }
}
