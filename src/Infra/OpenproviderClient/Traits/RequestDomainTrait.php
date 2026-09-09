<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Traits;

use Exception;
use Waterfront\Domain\Domains\DTO\Domain;

trait RequestDomainTrait
{
    private Domain $domain;

    /**
     * @throws Exception
     */
    public function setDomain(string $domain): void
    {
        $this->domain = new Domain($domain);
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }
}
