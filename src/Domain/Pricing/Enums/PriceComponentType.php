<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Enums;

enum PriceComponentType: string
{
    case REGISTRATION = 'registration';
    case PRODUCT_GROUP = 'product-group';
    case REGISTRATION_STAFFEL = 'registration-staffel';
    case PROLONGATION_STAFFEL = 'prolongation-staffel';
    case INTRODUCTION = 'introduction';
    case PROLONGATION = 'prolongation';
    case PROMOTION = 'promotion';
    case PRO_RATE = 'pro-rate';
    case VOUCHER = 'voucher';
    // A custom subscription price for every invoice within the contract period.
    case CUSTOM_ONE_OFF = 'custom-one-off';
    // An indefinite custom subscription price.
    case CUSTOM_INDEFINITE = 'custom-indefinite';
    case EXPERIMENT_PRICE_LADDER = 'experiment-price-ladder';
}
