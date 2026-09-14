<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ResellerHostingUnableToModifySettingsException extends Exception
{
    public function __construct(Subscription $subscription, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Unable to change DNS management to {false} and SSO setting to {true} the hosting backend for subscription ID: {%d}',
                $subscription->id,
            ),
            0,
            $previous,
        );
    }
}
