<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Repositories;

use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;

class BasekitSitebuilderDeploymentRepository
{
    public function create(SitebuilderDeployment $sitebuilderDeployment, int $siteReference): BasekitSitebuilderDeployment
    {
        $basekitSitebuilderDeployment = new BasekitSitebuilderDeployment();
        $basekitSitebuilderDeployment->uuid = Uuid::uuid4();
        $basekitSitebuilderDeployment->sitebuilder_deployment_id = $sitebuilderDeployment->id;
        $basekitSitebuilderDeployment->site_ref = $siteReference;
        $basekitSitebuilderDeployment->save();

        return $basekitSitebuilderDeployment;
    }
}
