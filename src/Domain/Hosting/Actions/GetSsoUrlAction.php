<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use Waterfront\Domain\Hosting\Actions\BaseKit\BaseKitGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\DirectAdmin\DirectAdminGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskGetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Exceptions\NotImplementedException;

class GetSsoUrlAction
{
    public function __construct(
        private readonly DirectAdminGetSsoUrlAction $directAdminGetSsoUrlAction,
        private readonly PleskGetSsoUrlAction $pleskGetSsoUrlAction,
        private readonly BaseKitGetSsoUrlAction $baseKitGetSsoUrlAction,
    ) {
    }

    /**
     * @throws SsoResolveException
     * @throws NotImplementedException
     */
    public function execute(
        Server $server,
        ?string $username,
        string $ipAddress = '',
        bool $redirectToMail = false,
        string $siteRef = '',
    ): string {
        if ($username === '' || $username === null) {
            throw new SsoResolveException('Tried to resolve SSO but no username was supplied');
        }

        $ssoUrl = match ($server->type) {
            ServerType::DIRECTADMIN => $this->directAdminGetSsoUrlAction->execute(
                $server,
                $username,
            ),
            ServerType::PLESK => $this->pleskGetSsoUrlAction->execute(
                $server,
                $username,
                $ipAddress,
                $redirectToMail,
            ),
            ServerType::SITEBUILDER => $this->baseKitGetSsoUrlAction->execute(
                $server,
                (int) $username,
                (int) $siteRef,
            ),
            default => throw new NotImplementedException(sprintf(
                'Server type "%s" not supported to generate SSO url with.',
                $server->type->value,
            )),
        };

        if ($ssoUrl === '') {
            throw new SsoResolveException('Failed to generate Sso url');
        }

        return $ssoUrl;
    }
}
