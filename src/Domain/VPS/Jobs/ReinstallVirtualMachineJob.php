<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Services\VirtualMachineService;

class ReinstallVirtualMachineJob extends CloudstackAsyncJob
{
    final protected const string JOB_TYPE = 'REINSTALL';

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        // if a re-installation fails the original VPS is still operational
        $this->deployment->subscription->technical_status = TechnicalStatus::OK->value;
        $this->deployment->subscription->save();

        $this->deployment->last_action_status = VpsActionStatus::REINSTALLING_FAILED;
        $this->deployment->save();
    }

    /**
     * @throws CloudstackNotFoundException
     * @throws BindingResolutionException
     */
    protected function handleSuccess(AsynchronousCloudstackResponse $jobResponse): void
    {
        parent::handleSuccess($jobResponse);

        /** @var VirtualMachineService $virtualMachineService */
        $virtualMachineService = Container::getInstance()->make(VirtualMachineService::class);

        $virtualMachineService->stop($this->deployment);

        $dispatcher = Container::getInstance()->make(Dispatcher::class);
        $dispatcher->dispatch(new WaitForVirtualMachineStoppedSetCredentialsJob(
            $this->deployment,
            $this->cloudstackJob,
            $jobResponse
        ));
    }

    protected function handlePending(): void
    {
        parent::handlePending();

        if ($this->deployment->last_action_status !== VpsActionStatus::REINSTALLING) {
            $this->deployment->last_action_status = VpsActionStatus::REINSTALLING;
            $this->deployment->save();
        }
    }
}
