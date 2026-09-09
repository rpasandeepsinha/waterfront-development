<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Exceptions;

use Exception;

class ResellerHostingSslCoupleException extends Exception
{
    public static function installCertificateError(string $domain, int $code = 0, ?Exception $previous = null): self
    {
        return new self(
            sprintf(
                'There was an error coupling the certificate for domain %s',
                $domain
            ),
            $code,
            $previous
        );
    }
}
