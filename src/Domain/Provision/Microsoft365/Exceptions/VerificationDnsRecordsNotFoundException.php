<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Exceptions;

class VerificationDnsRecordsNotFoundException extends Microsoft365Exception
{
    public function __construct(string $domain)
    {
        parent::__construct(message: sprintf('Unable to retrieve Verification DNS records for domain [%s]', $domain));
    }
}
