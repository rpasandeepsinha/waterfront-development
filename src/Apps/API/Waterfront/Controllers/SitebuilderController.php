<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Webmozart\Assert\Assert;

class SitebuilderController
{
    public function __construct(
        private readonly SitebuilderService $sitebuilderService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getSsoUrl(HostingDeployment $hostingDeployment): JsonResponse
    {
        $domain = $hostingDeployment->subscription->domain;

        Assert::notNull($domain, 'Provided subscription has no domain');

        try {
            $ssoUrl = $this->sitebuilderService->getSsoUrl($hostingDeployment);
        } catch (UnexpectedValueException|ServerNotFoundException|InvalidArgumentException $e) {
            $this->logger->error(sprintf(
                'Failed to generate sso url for domain : {%s} message: {%s}',
                $domain,
                $e->getMessage()
            ));
            return new JsonResponse(['message' => 'Failed to generate SSO Url'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['url' => $ssoUrl]);
    }
}
