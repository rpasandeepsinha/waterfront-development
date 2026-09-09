<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Exceptions;

use RuntimeException;
use Throwable;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminException;

class MailOnlyException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public ?string $domain = null,
        public ?Server $server = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromDirectAdminResponseException(DirectAdminException $exception, Server $server, string $domain): MailOnlyException
    {
        return new MailOnlyException(
            $exception->getMessage(),
            $exception->getCode(),
            $exception->getPrevious(),
            $domain,
            $server
        );
    }
}
