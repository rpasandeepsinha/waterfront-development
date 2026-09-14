<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;

class DestroyVirtualMachineJob extends CloudstackAsyncJob
{
    final protected const string JOB_TYPE = 'DESTROY';

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        $this->deployment->subscription->technical_status = TechnicalStatus::DELETING_FAILED->value;
        $this->deployment->subscription->save();
    }

    /**
     * @throws CloudstackNotFoundException
     */
    protected function handleSuccess(AsynchronousCloudstackResponse $jobResponse): void
    {
        parent::handleSuccess($jobResponse);

        if ($this->deployment->last_action_status === VpsActionStatus::DELETING_FOR_REDEPLOY) {
            $this->deployment->subscription->technical_status = TechnicalStatus::DELETING->value;
            $this->deployment->subscription->save();

            Container::getInstance()
                ->make(Dispatcher::class)
                ->dispatch(new WaitForVirtualMachineDestroyedThenRedeployJob(
                    deployment: $this->deployment,
                    cloudstackJob: $this->cloudstackJob,
                    sshKeyUuid: $this->cloudstackJob->ssh_key_uuid,
                ));

            return;
        }

        $this->deployment->subscription->technical_status = TechnicalStatus::DELETED->value;
        $this->deployment->subscription->save();

        $this->deployment->delete();
    }

    protected function handlePending(): void
    {
        if ($this->deployment->subscription->technical_status !== TechnicalStatus::DELETING->value) {
            $this->deployment->subscription->technical_status = TechnicalStatus::DELETING->value;
            $this->deployment->subscription->save();
        }
    }
}
