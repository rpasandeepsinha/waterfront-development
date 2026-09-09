<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Waterfront\Domain\Transfers\Interfaces\ExecuteExtensionTransferInterface;
use Waterfront\Domain\Transfers\Interfaces\ExecuteTransferInterface;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Observers\TransferObserver;
use Waterfront\Domain\Transfers\Services\ExecuteExtensionTransferService;
use Waterfront\Domain\Transfers\Services\ExecuteTransferService;
use Waterfront\Support\Providers\BaseProvider;

class TransfersServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        Transfer::observe(TransferObserver::class);
    }

    public function register(): void
    {
        $this->app->bind(ExecuteTransferInterface::class, fn () => $this->resolve(ExecuteTransferService::class));
        $this->app->bind(ExecuteExtensionTransferInterface::class, ExecuteExtensionTransferService::class);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ExecuteTransferInterface::class,
            ExecuteExtensionTransferInterface::class,
        ];
    }
}
