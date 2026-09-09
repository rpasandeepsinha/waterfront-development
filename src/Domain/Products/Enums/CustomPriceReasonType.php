<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

enum CustomPriceReasonType: string
{
    case MANUAL_NOVA_OVERRIDE = 'manual-nova-override';
    case MANUAL_COMPASS_OVERRIDE = 'manual-compass-override';
    case FIXED_MIGRATION_PRICE = 'fixed-migration-price';
    // The order line the subscription came from carried a custom indefinite price, so that price governs
    // the subscription rather than the product's own. See OrderService::processMutations().
    case ORDER_LINE_CUSTOM_PRICE = 'order-line-custom-price';
    // LEGACY_BACKFILL is only used for backfilling reasons in 2026. For all existing subscriptions we can't calculate
    // their price composition anymore. We just attach a custom price component to existing subscriptions, so every
    // subscription still has some kind of reasoning attached.
    case LEGACY_BACKFILL = 'legacy-backfill';
}
