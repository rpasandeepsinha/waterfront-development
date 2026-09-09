<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Exceptions;

use Exception;

class HarborApiResponseException extends Exception
{
    public static function responseError(int $statusCode, string $message): self
    {
        return new HarborApiResponseException(
            sprintf(
                'The API responded with a error =>  Statuscode : %d | Message : %s',
                $statusCode,
                $message
            )
        );
    }
}
