<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Policies;

use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Hosting\Actions\FetchUserFromServerAction;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Authentication\AuthenticationManager;

class ServerPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAvailableCompassActions(Server $server): array
    {
        $actions = [];

        if ($this->authManager->getAuthenticatedSubject()->identitySchema->schemaId !== SchemaId::EMPLOYEE) {
            return $actions;
        }

        if (FetchUserFromServerAction::supports($server->type)) {
            $actions[] = 'fetchServerUser';
        }

        return $actions;
    }
}
