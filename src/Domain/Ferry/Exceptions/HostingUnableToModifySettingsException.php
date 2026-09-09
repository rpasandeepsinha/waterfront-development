<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HostingUnableToModifySettingsException extends Exception
{
    public function __construct(Subscription $subscription, bool $dnsSetting, bool $ssoSetting, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Unable to change DNS management to {%b} and SSO setting to {%b} the hosting backend for subscription ID: {%d}',
                $dnsSetting,
                $ssoSetting,
                $subscription->id,
            ),
            0,
            $previous
        );
    }
}
