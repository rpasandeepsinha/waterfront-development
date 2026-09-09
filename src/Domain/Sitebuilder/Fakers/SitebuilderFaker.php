<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Fakers;

use SandwaveIo\BaseKit\Domain\Domain;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderSiteInterface;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderUserInterface;
use Waterfront\Domain\Sitebuilder\Interfaces\SitebuilderDriverInterface;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SitebuilderFaker implements SitebuilderDriverInterface
{
    public function createSite(Subscription $subscription, Server $server, Server $mailOnlyServer): Result
    {
        $result =  new Result();
        $result->setResponseBody([
            'ref' => 1,
            'domains' => [
                0 => [
                    'ref' => 1,
                    'domainName' => 'example.com',
                ],
            ],
            'contentMapSite' => null,
            'template' => null,
            'primaryDomain' => new Domain(1, 'example.com'),
            'lastPublish' => null,
            'brandRef' => 1,
            'version' => 69,
            'enabled' => true,
            'privateWidgets' => null,
            'mobileSiteRef' => null,
            'mobile' => false,
            'profileRef' => null,
        ]);

        return $result;
    }

    public function setupSsl(SslDeployment $sslDeployment, Server $server): Result
    {
        return new Result();
    }

    public function deleteSite(HostingDeployment $hostingHostingSubscription, Server $server): void
    {
    }

    public function getSsoUrl(Server $server, string $domain, int $basekitUserRef, int $basekitSiteRef): string
    {
        return 'https://fakessourl.testing/';
    }

    public function getSite(HostingDeployment $hostingDeployment): SitebuilderSiteInterface
    {
        return new BaseKitSite(
            id: 123,
            domain: 'fake-sitebuilder.test',
        );
    }

    public function getSiteFromRef(int $siteRef, Server $server): SitebuilderSiteInterface
    {
        return new BaseKitSite(
            id: 123,
            domain: 'fake-sitebuilder.test',
        );
    }

    public function getUserFromRef(int $userRef, Server $server): SitebuilderUserInterface
    {
        return new BaseKitUser(
            id: 456,
            email: 'fake@email.test',
        );
    }
}
