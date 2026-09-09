<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Interfaces;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Interfaces\ProvisionTypeValidatorInterface;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;

interface SitebuilderValidatorInterface extends ProvisionTypeValidatorInterface
{
    public function getSsoRequestValidator(GetSitebuilderSsoRequest $request): Validator;

    public function getTerminateContextRequestValidator(TerminateSitebuilderContextRequest $terminateRequest): Validator;

    public function getAddSslSitebuilderValidator(AddSslSitebuilderRequest $request): Validator;

    public function getTerminateSitebuilderSiteRequestValidator(TerminateSitebuilderRequest $request): Validator;

    public function getGetBasekitSiteByRefRequestValidator(GetBasekitSiteByRefRequest $request): Validator;
}
