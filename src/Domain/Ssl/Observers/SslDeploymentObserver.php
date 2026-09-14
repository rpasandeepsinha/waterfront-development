<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Observers;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Models\SslDeployment;

class SslDeploymentObserver
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function updated(SslDeployment $sslDeployment): void
    {
        if ($sslDeployment->isDirty('certificate_id')) {
            $this->dispatcher->dispatch(
                new UpdateSslExpireDate(
                    sslDeployment: $sslDeployment,
                ),
            );
        }
    }
}
