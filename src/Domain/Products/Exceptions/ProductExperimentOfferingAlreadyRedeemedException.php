<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Exceptions;

use Exception;

/**
 * The customer is enrolled, but has already spent their single redemption of the offering. Separate
 * from ProductExperimentOfferingNotClaimableException so callers can tell "you already had this" from
 * "this was never yours to take", which are very different things to tell a customer.
 */
class ProductExperimentOfferingAlreadyRedeemedException extends Exception
{
}
