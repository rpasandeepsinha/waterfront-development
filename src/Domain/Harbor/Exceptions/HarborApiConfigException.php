<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Exceptions;

use Exception;
use Throwable;

class HarborApiConfigException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf(
            'The mandatory configvar : %s is missing or empty. Check the config file : %s',
            $message,
            'Harbor/Config/connection.php',
        );

        parent::__construct($message, $code, $previous);
    }
}
