<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions\BaseKit;

use Psr\Log\LoggerInterface;
use SandwaveIo\BaseKit\Exceptions\BaseKitClientException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;

class BaseKitGetSsoUrlAction
{
    public function __construct(
        private readonly BasekitFactoryInterface $basekitFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Server $server, int $basekitUserRef, int $basekitSiteRef): string
    {
        $basekitClient = $this->basekitFactory->make($server);

        try {
            $hash = $basekitClient->loginApi->autoLogin($basekitUserRef);
        } catch (BaseKitClientException $exception) {
            $this->logger->error(
                sprintf(
                    '%s, with basekit user ref: %s',
                    $exception->getMessage(),
                    $basekitUserRef,
                ),
            );
            throw new SitebuilderException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return sprintf('https://flow.%s/login?hash=%s&siteRef=%s', $server->domain, $hash, $basekitSiteRef);
    }
}
