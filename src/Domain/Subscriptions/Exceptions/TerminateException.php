<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions;

use Exception;

class TerminateException extends Exception
{
    public static function parentSubscriptionExpected(): TerminateException
    {
        return new self('Can not terminate child subscription directly');
    }
}
