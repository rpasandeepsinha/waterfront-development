<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Interfaces;

use Waterfront\Domain\Provision\Interfaces\TypeProvisionServiceInterface;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderSsoResult;

interface SitebuilderProvisionServiceInterface extends TypeProvisionServiceInterface
{
    public function create(CreateSitebuilderRequest $createSitebuilderRequest): SitebuilderResult;

    public function getSso(GetSitebuilderSsoRequest $provisionData): SitebuilderSsoResult;

    public function terminateByContext(TerminateSitebuilderContextRequest $provisionData): SitebuilderResult;

    public function addSsl(AddSslSitebuilderRequest $provisionData): SitebuilderResult;

    public function terminateSitebuilder(TerminateSitebuilderRequest $provisionData): SitebuilderResult;

    public function update(UpdateSitebuilderRequest $updateSitebuilderRequest): SitebuilderResult;
}
