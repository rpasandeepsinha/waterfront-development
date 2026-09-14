<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class GetNameServers extends DirectAdminCommand
{
    protected string $command = 'CMD_API_NAME_SERVER';

    protected string $method = 'GET';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    public function getNS1(): string
    {
        $NS = $this->getFormValues()['NS1'];
        assert(is_string($NS));

        return $NS;
    }

    public function getNS2(): string
    {
        $NS = $this->getFormValues()['NS2'];
        assert(is_string($NS));

        return $NS;
    }
}
