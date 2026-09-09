<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Actions;

use Waterfront\Domain\Servers\DTO\ServerDTO;
use Waterfront\Domain\Servers\Models\Server;

class UpdateServerAction
{
    public function execute(Server $server, ServerDTO $serverData): Server
    {
        $server->type = $serverData->type;
        $server->hostname = $serverData->hostname;
        $server->port = $serverData->port;
        $server->use_ssl = $serverData->useSsl;
        $server->allow_new_websites = $serverData->allowNewWebsites;
        $server->name = $serverData->name;
        $server->owner = $serverData->owner;
        $server->ipv4 = $serverData->ipv4;
        $server->ipv6 = $serverData->ipv6;
        $server->username = $serverData->username;
        $server->maximum_websites = $serverData->maximumWebsites;

        if ($serverData->password !== null) {
            $server->password = $serverData->password;
        }

        if ($serverData->loginKey !== null) {
            $server->loginkey = $serverData->loginKey;
        }

        if ($serverData->secretKey !== null) {
            $server->secret_key = $serverData->secretKey;
        }

        $server->save();

        return $server;
    }
}
