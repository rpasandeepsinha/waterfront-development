<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Exceptions;

use Exception;
use Saloon\Http\Response;
use Throwable;

class PuzzelResponseMissingRedirectException extends Exception
{
    public function __construct(Response $response, int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf('Missing redirect in the response from Puzzel. Response: [%s]', $response->body());
        parent::__construct($message, $code, $previous);
    }
}
