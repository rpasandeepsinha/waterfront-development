<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\VirtualMachineService;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class WaitForVirtualMachineStoppedSetCredentialsJob extends AbstractQueueableJob
{
    public int $tries = 5;

    public function __construct(
        protected VirtualMachineDeployment $deployment,
        protected CloudstackJob $cloudstackJob,
        protected AsynchronousCloudstackResponse $jobResponse,
    ) {
        parent::__construct();
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [
            10,
            20,
            30,
            60,
            360,
        ];
    }

    public function failed(?Throwable $exception): void
    {
        $logger = Container::getInstance()->make(LoggerInterface::class);
        $logger->warning(
            'VM failed to stop during reinstall, setting technical status to OK.',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_ID => $this->deployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'job_id' => $this->cloudstackJob->job_id,
                ],
            ],
        );

        // If waiting for a stopped vm fails, the VM is still operational for the customer
        $this->deployment->subscription->technical_status = TechnicalStatus::OK->value;
        $this->deployment->subscription->save();

        $this->deployment->last_action_status = VpsActionStatus::REINSTALLING_FAILED;
        $this->deployment->save();
    }

    public function handle(
        VirtualMachineService $virtualMachineService,
        VpsService $vpsService,
        LoggerInterface $logger,
    ): void {
        $virtualMachine = $virtualMachineService->findByDeployment($this->deployment);
        assert($virtualMachine !== null);

        if ($virtualMachine->state !== CloudstackMachineState::STOPPED) {
            $logger->info(
                sprintf(
                    'Waiting for VM to stop, current state: %s, attempt: [%d/%d]',
                    $virtualMachine->state->value,
                    $this->attempts(),
                    $this->tries,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_ID => $this->deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::META => [
                        'job_id' => $this->cloudstackJob->job_id,
                    ],
                ],
            );

            $this->release($this->getBackoffDelay());

            return;
        }

        $serializer = CloudstackSerializerFactory::get();
        /** @var VirtualMachine $vpsData */
        $vpsData = $serializer->denormalize(
            $this->jobResponse->retrieveReinstallData(),
            VirtualMachine::class,
        );
        $vpsService->mailCustomerVmDetails($this->deployment, $vpsData);

        $virtualMachineService->postReinstall(
            $this->deployment,
            $this->cloudstackJob,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CLOUDSTACK;
    }
}
