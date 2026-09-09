<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Interfaces;

use Waterfront\Infra\RtrClient\DTO\Revision;
use Waterfront\Infra\RtrClient\Exceptions\RtrApiException;

interface RevisionInterface
{
    /**
     * @throws RtrApiException
     *
     * @return Revision[]
     */
    public function revisions(string $domain): array;
}
