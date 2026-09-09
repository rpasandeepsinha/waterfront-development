<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;

class ResetPasswordJob extends CloudstackAsyncJob
{
    final protected const string JOB_TYPE = 'RESET_PASSWORD';

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        // if resetting credentials fails the original VPS is still operational
        $this->deployment->subscription->technical_status = TechnicalStatus::OK->value;
        $this->deployment->subscription->save();

        $this->deployment->last_action_status = VpsActionStatus::RESET_CREDENTIALS_FAILED;
        $this->deployment->save();
    }

    /**
     * @throws CloudstackNotFoundException
     * @throws BindingResolutionException
     */
    protected function handleSuccess(AsynchronousCloudstackResponse $jobResponse): void
    {
        parent::handleSuccess($jobResponse);

        $this->deployment->subscription->technical_status = TechnicalStatus::OK->value;
        $this->deployment->subscription->save();

        $this->deployment->last_action_status = VpsActionStatus::RESET_CREDENTIALS_SUCCESS;
        $this->deployment->save();
    }

    protected function handlePending(): void
    {
        parent::handlePending();

        if ($this->deployment->last_action_status !== VpsActionStatus::RESETTING_CREDENTIALS) {
            $this->deployment->last_action_status = VpsActionStatus::RESETTING_CREDENTIALS;
            $this->deployment->save();
        }
    }
}
