<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DirectAdmin\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectadminUsernameBroker;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingUsernameInterface;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Connection\Server;
use Waterfront\Infra\DirectAdminClient\Fakers\DirectAdmin as FakeDirectAdmin;

class DirectAdminHostingServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/directadmin.php',
            'hostingservice',
        );
    }

    public function register(): void
    {
        $useFaker = boolval(Env::get('APP_FAKE_DIRECTADMIN_CLIENT'));
        if ($useFaker) {
            $this->app->singleton(BehavesAsDirectAdmin::class, fn () => new FakeDirectAdmin(new Server()));
        }

        $this->app->bind(HostingUsernameInterface::class, DirectadminUsernameBroker::class);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            BehavesAsDirectAdmin::class,
            HostingUsernameInterface::class,
        ];
    }
}
