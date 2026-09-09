<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Enums;

enum RetentionOfferEligibilityCode: string
{
    case ELIGIBLE = 'eligible';
    case NO_PRICE_REQUIRED = 'no_price_required';
    case INELIGIBLE_PRODUCT = 'ineligible_product';
    case INACTIVE_SUBSCRIPTION = 'inactive_subscription';
    case INVALID_CONTRACT_PERIOD = 'invalid_contract_period';
    case INVALID_BILLING_PERIOD = 'invalid_billing_period';
    case MISSING_PRICE = 'missing_price';
    case OPEN_MUTATION = 'open_mutation';
}
