<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;

class ResetSshKeyForVirtualMachineJob extends CloudstackAsyncJob
{
    final protected const string JOB_TYPE = 'RESET_SSH_KEY';

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        // if resetting the ssh key fails, the original VPS is still operational
        $this->deployment->subscription->technical_status = TechnicalStatus::OK->value;
        $this->deployment->subscription->save();

        $this->deployment->last_action_status = VpsActionStatus::RESET_SSH_KEY_FAILED;
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

        $this->deployment->last_action_status = VpsActionStatus::RESET_SSH_KEY_SUCCESS;
        $this->deployment->save();
    }

    protected function handlePending(): void
    {
        parent::handlePending();

        if ($this->deployment->last_action_status !== VpsActionStatus::RESETTING_SSH_KEY) {
            $this->deployment->last_action_status = VpsActionStatus::RESETTING_SSH_KEY;
            $this->deployment->save();
        }
    }
}
