<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Repositories;

use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Redirects\Models\CaddyRedirectDeployment;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;

class CaddyRedirectDeploymentRepository
{
    public function create(RedirectDeployment $redirectDeployment, string $caddy_id): CaddyRedirectDeployment
    {
        $caddyRedirectDeployment = new CaddyRedirectDeployment();
        $caddyRedirectDeployment->uuid = Uuid::uuid4();
        $caddyRedirectDeployment->redirect_deployment_id = $redirectDeployment->id;
        $caddyRedirectDeployment->caddy_id = $caddy_id;
        $caddyRedirectDeployment->save();

        return $caddyRedirectDeployment;
    }

    public function forceDelete(CaddyRedirectDeployment $caddyRedirectDeployment): void
    {
        $caddyRedirectDeployment->forceDelete();
    }

    public function deleteDeploymentWithParent(CaddyRedirectDeployment $caddyRedirectDeployment): void
    {
        $caddyRedirectDeployment->delete();
        $caddyRedirectDeployment->redirectDeployment->delete();
    }
}
