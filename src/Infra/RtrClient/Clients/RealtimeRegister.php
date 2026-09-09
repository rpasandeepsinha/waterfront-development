<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Clients;

use RealtimeRegister\RealtimeRegister as RealtimeRegisterPackage;
use RealtimeRegister\Support\AuthorizedClient;

class RealtimeRegister extends RealtimeRegisterPackage
{
    public RevisionApi $revisions;

    public function setClient(AuthorizedClient $client): void
    {
        parent::setClient($client);
        $this->revisions = new RevisionApi($client);
    }
}
