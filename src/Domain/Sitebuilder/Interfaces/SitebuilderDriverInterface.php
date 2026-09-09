<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Interfaces;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderSiteInterface;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderUserInterface;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

interface SitebuilderDriverInterface
{
    public function createSite(Subscription $subscription, Server $server, Server $mailOnlyServer): Result;

    public function getSite(HostingDeployment $hostingDeployment): SitebuilderSiteInterface;

    public function getSiteFromRef(int $siteRef, Server $server): SitebuilderSiteInterface;

    public function getUserFromRef(int $userRef, Server $server): SitebuilderUserInterface;

    public function setupSsl(SslDeployment $sslDeployment, Server $server): Result;

    public function getSsoUrl(Server $server, string $domain, int $basekitUserRef, int $basekitSiteRef): string;

    public function deleteSite(HostingDeployment $hostingDeployment, Server $server): void;
}
