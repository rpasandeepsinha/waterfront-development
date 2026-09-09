<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Container\Container;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

class DeployVirtualMachineJob extends CloudstackAsyncJob
{
    protected const string JOB_TYPE = 'DEPLOY';

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        $this->handleTechnicalStatusSubscription(TechnicalStatus::FAILED->value);
    }

    protected function handleSuccess(AsynchronousCloudstackResponse $jobResponse): void
    {
        parent::handleSuccess($jobResponse);

        $serializer = CloudstackSerializerFactory::get();
        $vmData = $serializer->denormalize($jobResponse->retrieveVirtualMachineData(), VirtualMachine::class);

        $this->deployment->cloudstack_id = $vmData->id;
        $this->deployment->save();

        $this->handleTechnicalStatusSubscription(TechnicalStatus::OK->value);

        Container::getInstance()->make(VpsService::class)->mailCustomerVmDetails($this->deployment, $vmData);
    }

    private function handleTechnicalStatusSubscription(string $technicalStatus): void
    {
        $this->deployment->subscription->technical_status = $technicalStatus;
        $this->deployment->subscription->save();

        $this->deployment->subscription->children()->update([
            'technical_status' => $technicalStatus,
        ]);
    }
}
