<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions\Plesk;

use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Servers\Models\Server;

class PleskGetSsoUrlAction
{
    public function __construct(private readonly SessionTokenInterface $sessionTokenClient)
    {
    }

    public function execute(Server $server, string $username, string $ipAddress, bool $redirectToMail): string
    {
        $this->sessionTokenClient->setServer($server);

        return $this->sessionTokenClient->getSsoUrl(
            $username,
            $ipAddress,
            $redirectToMail
        );
    }
}
