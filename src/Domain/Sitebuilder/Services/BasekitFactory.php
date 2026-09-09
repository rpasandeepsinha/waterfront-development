<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Services;

use SandwaveIo\BaseKit\BaseKit;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;

class BasekitFactory implements BasekitFactoryInterface
{
    /**
     * Creates an instance of basekitClient based on the given server.
     */
    public function make(Server $server): BaseKit
    {
        $username = $server->username;
        $password = $server->password;

        if (is_null($username) || is_null($password)) {
            throw new SitebuilderException(sprintf(
                'Credentials to from server with id: %s, have not been set.',
                $server->id
            ));
        }

        return new BaseKit($username, $password, $server->hostname);
    }
}
