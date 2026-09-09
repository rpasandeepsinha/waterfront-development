<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Interfaces;

use Waterfront\Domain\Provision\Interfaces\TypeProvisionServiceInterface;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;

interface RedirectProvisionServiceInterface extends TypeProvisionServiceInterface
{
    public function createRedirect(CreateRedirectRequest $provisionData): RedirectResult;

    public function getRedirect(GetRedirectRequest $provisionData): GetRedirectResult;

    public function listRedirects(ListRedirectsRequest $provisionData): ListRedirectResult;

    public function updateRedirect(UpdateRedirectRequest $provisionData): RedirectResult;

    public function deleteRedirect(DeleteRedirectRequest $provisionData): RedirectResult;

    public function terminateRedirects(TerminateRedirectsRequest $provisionData): RedirectResult;

    public function suspendRedirects(SuspendRedirectRequest $provisionData): RedirectResult;

    public function unsuspendRedirects(UnsuspendRedirectRequest $provisionData): RedirectResult;
}
