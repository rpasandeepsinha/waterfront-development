<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Helpers\Url;

class SubdomainServiceProvider extends BaseProvider
{
    private string $subdomain;

    private string $appName;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function register(): void
    {
        $configuration = $this->resolve(ConfigurationInterface::class);
        $urlHelper = $this->resolve(Url::class);
        $request = $this->resolve(Request::class);

        $subdomain = $urlHelper->getSubdomain($request);
        if ($subdomain === null) {
            return;
        }

        $this->appName = Str::slug($configuration->getAsString('app.name'));
        $this->subdomain = $subdomain;

        $this->setSessionName();
        $this->setCacheName();
    }

    private function setSessionName(): void
    {
        Config::set('session.cookie', $this->subdomain . '_' . $this->appName . '_session');
    }

    private function setCacheName(): void
    {
        Config::set('cache.prefix', $this->subdomain . '_' . $this->appName . '_cache');
    }
}
