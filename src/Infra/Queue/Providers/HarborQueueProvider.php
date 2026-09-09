<?php

declare(strict_types=1);

namespace Waterfront\Infra\Queue\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use SandwaveIo\HarborMessages\Message\Serializer\JsonSerializer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Queue\HarborQueue;
use Waterfront\Support\Providers\BaseProvider;

class HarborQueueProvider extends BaseProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(HarborQueue::class, fn (): HarborQueue => new HarborQueue(
            $this->resolve(ConfigurationInterface::class),
            new JsonSerializer(),
        ));
    }

    /** @return class-string[] */
    public function provides(): array
    {
        return [HarborQueue::class];
    }
}
