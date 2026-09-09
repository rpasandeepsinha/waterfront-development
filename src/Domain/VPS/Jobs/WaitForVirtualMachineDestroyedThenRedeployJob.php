<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Container\Container;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\VpsService;

class WaitForVirtualMachineDestroyedThenRedeployJob extends CloudstackAsyncJob
{
    final protected const string JOB_TYPE = 'WAIT_DESTROY_REDEPLOY';

    public function __construct(
        VirtualMachineDeployment $deployment,
        CloudstackJob $cloudstackJob,
        private readonly ?string $sshKeyUuid,
    ) {
        parent::__construct($deployment, $cloudstackJob);
    }

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        $this->deployment->subscription->technical_status = TechnicalStatus::DELETING_FAILED->value;
        $this->deployment->subscription->save();

        $this->deployment->last_action_status = VpsActionStatus::DELETING_FOR_REDEPLOY_FAILED;
        $this->deployment->save();
    }

    protected function handleSuccess(AsynchronousCloudstackResponse $jobResponse): void
    {
        parent::handleSuccess($jobResponse);

        $virtualMachineService = Container::getInstance()->make(VirtualMachineServiceInterface::class);

        if ($virtualMachineService->findByDeployment($this->deployment) !== null) {
            $this->handlePending();
            $this->release(static::RETRY_DELAY_SECONDS * $this->attempts());
            return;
        }

        $this->deployment->cloudstack_id = null;
        $this->deployment->last_action_status = null;
        $this->deployment->save();

        $subscription = $this->deployment->subscription->refresh();
        $subscription->technical_status = TechnicalStatus::PENDING->value;
        $subscription->save();
        $subscription->children()->update(['technical_status' => TechnicalStatus::PENDING->value]);

        $vpsService = Container::getInstance()->make(VpsService::class);
        $vpsService->create(subscription: $subscription, sshKeyUuid: $this->sshKeyUuid);
    }

    protected function handlePending(): void
    {
        $this->deployment->subscription->technical_status = TechnicalStatus::DELETING->value;
        $this->deployment->subscription->save();
    }
}
