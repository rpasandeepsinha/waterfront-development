<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\VPS\Actions\ResolveAndLinkSshKeyAction;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VirtualMachineState;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Exceptions\NoCloudstackTemplateFoundFromProduct;
use Waterfront\Domain\VPS\Exceptions\NonVpsOsProductException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Jobs\DestroyVirtualMachineJob;
use Waterfront\Domain\VPS\Jobs\ReinstallVirtualMachineJob;
use Waterfront\Domain\VPS\Jobs\ResetPasswordJob;
use Waterfront\Domain\VPS\Jobs\ResetSshKeyForVirtualMachineJob;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class VirtualMachineService implements VirtualMachineServiceInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $clientFactory,
        private readonly LoggerInterface $logger,
        private readonly Dispatcher $bus,
        private readonly Serializer $serializer,
        private readonly VpsTemplateService $vpsTemplateService,
        private readonly ProductRepository $productRepository,
        private readonly ResolveAndLinkSshKeyAction $linkSshKeyAction,
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        public readonly AdminClientFactory $adminClientFactory,
    ) {
    }

    /**
     * @throws CloudstackNotFoundException
     */
    public function findByDeployment(VirtualMachineDeployment $deployment): ?VirtualMachine
    {
        if ($deployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to retrieve details of a VM deployment ({provisioning.id}) without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return null;
        }

        try {
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);
        } catch (ClientFactoryException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return null;
        }

        return $client->getVirtualMachine($deployment->cloudstack_id);
    }

    /**
     * @throws CloudstackNotFoundException
     * @throws ClientException
     *
     * @return VirtualMachine[]
     *
     */
    public function findVirtualMachines(ManagerDomainDeployment $mdDeployment): array
    {
        try {
            $client = $this->clientFactory->create($mdDeployment);
        } catch (ClientFactoryException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::META => [
                    'manager_domain_deployment' => $mdDeployment->toArray(),
                ],
            ]);

            return [];
        }

        // This client is created with the manager domain deployment, so
        // it will list the VMs for that manager domain and no other.
        return $client->listAllVirtualMachines();
    }

    public function start(VirtualMachineDeployment $deployment): bool
    {
        if ($deployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to start a VM deployment ({provisioning.id}) without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return false;
        }

        try {
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);

            $client->startVirtualMachine($deployment->cloudstack_id);
        } catch (ClientFactoryException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function stop(VirtualMachineDeployment $deployment): bool
    {
        if ($deployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to stop a VM deployment ({provisioning.id}) without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return false;
        }

        try {
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);

            $client->stopVirtualMachine($deployment->cloudstack_id);
        } catch (ClientFactoryException | ClientException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function reboot(VirtualMachineDeployment $deployment): bool
    {
        if ($deployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to reboot a VM deployment ({provisioning.id}) without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return false;
        }

        try {
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);

            $client->rebootVirtualMachine($deployment->cloudstack_id);
        } catch (ClientFactoryException | ClientException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * @return Collection<int, Product>
     */
    public function getAvailableReinstallOptions(Subscription $osSubscription): Collection
    {
        return $this->productAllowedChangeRepository
            ->getPotentialReinstallsForCustomer(
                $osSubscription->product
            );
    }

    public function reinstall(
        VirtualMachineDeployment $deployment,
        Product $newOs,
        ?string $sshKeyUuid = null
    ): bool {
        if ($deployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to reinstall a VM deployment without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );
            return false;
        }

        try {
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);
            $template = $this->vpsTemplateService->getTemplateByProduct(
                product: $newOs,
                environment: $deployment->managerDomainDeployment->environment
            );
        } catch (ClientFactoryException | NoCloudstackTemplateFoundFromProduct | NonVpsOsProductException $e) {
            $this->logger->error('Reinstall init failed: ' . $e->getMessage(), [
                LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::EXCEPTION => $e,
            ]);
            return false;
        }

        try {
            $asyncJob = $client->restoreVirtualMachine($deployment->cloudstack_id, $template->id);
        } catch (ClientException $e) {
            $this->logger->error('Cloudstack restore error: ' . $e->getMessage(), [
                LoggingContextKeys::PROVISIONING_ID => $deployment->cloudstack_id,
                LoggingContextKeys::EXCEPTION => $e,
            ]);
            return false;
        }

        $subscription = $deployment->subscription;
        $jobModel = $this->createCloudstackJob($asyncJob, $deployment, [
            'template_uuid' => $newOs->uuid,
            'ssh_key_uuid' => $sshKeyUuid,
        ]);

        $this->logger->info(
            sprintf(
                'Cloudstack reinstalling VM [%s] with job ID %s for subscription %s.',
                $deployment->cloudstack_id,
                $jobModel->job_id,
                $subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::META => [
                    'cloudstack_job' => $jobModel->toArray(),
                ],
            ]
        );

        $deployment->last_action_status = VpsActionStatus::REINSTALLING;
        $deployment->subscription->update(['technical_status' => TechnicalStatus::PENDING->value]);
        $deployment->save();

        $this->bus->dispatch(new ReinstallVirtualMachineJob(
            $deployment,
            $jobModel
        ));

        return true;
    }

    public function postReinstall(
        VirtualMachineDeployment $deployment,
        CloudstackJob $jobModel
    ): void {
        $templateUuid = $jobModel->template_uuid;
        if ($templateUuid === null) {
            throw new RuntimeException("Missing template_uuid on job {$jobModel->job_id}");
        }

        $product = $this->productRepository->findProductByUuid($templateUuid);
        $sshKeyNeeded = $this->productSpecRepository->booleanSpecificationIsTrue($product, ProductSpecName::SSH_KEY_REQUIRED);

        if ($sshKeyNeeded) {
            $this->logger->info(
                'Binding SSH key to VM after reinstall',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::META => [
                        'ssh_key_uuid' => $jobModel->ssh_key_uuid,
                        'template_uuid' => $jobModel->template_uuid,
                    ],
                ]
            );
            $this->bindAndResetSshKey($jobModel, $deployment);
        } else {
            $this->logger->info(
                'Detatching SSH key from the VM after reinstalling to a password enabled os',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );
            $deployment->sshKeys()->detach();
        }
        $deployment->subscription->technical_status = TechnicalStatus::OK->value;
        $deployment->last_action_status = VpsActionStatus::REINSTALL_SUCCESS;
        $deployment->subscription->save();
        $deployment->save();
    }

    public function destroy(VirtualMachineDeployment $vmDeployment, ?string $sshKeyUuid = null): bool
    {
        if ($vmDeployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to destroy a VM deployment ({provisioning.id}) without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $vmDeployment,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return false;
        }

        try {
            $subscription = $vmDeployment->subscription;
            $client = $this->clientFactory->create($vmDeployment->managerDomainDeployment);

            $destroyVirtualMachineJob = $client->destroyVirtualMachine($vmDeployment->cloudstack_id, true);

            $destroyVirtualMachineJob = new AsynchronousCloudstackResponse(jobId: $destroyVirtualMachineJob->jobId);
            $cloudstackJob = $this->createCloudstackJob($destroyVirtualMachineJob, $vmDeployment, [
                'ssh_key_uuid' => $sshKeyUuid,
            ]);

            $this->logger->info(
                sprintf(
                    'Cloudstack destroying VM [%s] with job ID %s for subscription %s.',
                    $vmDeployment->cloudstack_id,
                    $cloudstackJob->job_id,
                    $subscription->uuid,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => [
                        'cloudstack_job' => $cloudstackJob->toArray(),
                    ],
                ]
            );

            $this->bus->dispatch(new DestroyVirtualMachineJob(
                $vmDeployment,
                $cloudstackJob
            ));

            // Job has been dispatched so subscription status is deleting until job completes or fails
            $subscription->technical_status = TechnicalStatus::DELETING->value;
            $subscription->save();
        } catch (ClientFactoryException | ClientException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function handleByStateAndDeployment(string $state, VirtualMachineDeployment $deployment): bool
    {
        $vmState = VirtualMachineState::tryFrom($state);

        $deployment->last_action_status = null;
        $deployment->save();

        return match ($vmState) {
            VirtualMachineState::START => $this->start($deployment),
            VirtualMachineState::STOP => $this->stop($deployment),
            VirtualMachineState::REBOOT => $this->reboot($deployment),
            default => false,
        };
    }

    public function resetPassword(VirtualMachineDeployment $deployment, string $password): bool
    {
        if ($deployment->cloudstack_id === null) {
            $this->logger->warning(
                'Trying to reset password of VM deployment ({provisioning.id}) without a cloudstack ID.',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return false;
        }

        try {
            $subscription = $deployment->subscription;
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);

            $resetPasswordJob = $client->resetPasswordForVirtualMachine($deployment->cloudstack_id, $password);

            $virtualMachineJob = new AsynchronousCloudstackResponse(jobId: $resetPasswordJob->jobId);
            $cloudstackJob = $this->createCloudstackJob($virtualMachineJob, $deployment);

            $this->logger->info(
                sprintf(
                    'Cloudstack resetting VMs [%s] password with job ID %s for subscription %s.',
                    $deployment->cloudstack_id,
                    $cloudstackJob->job_id,
                    $subscription->uuid,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => [
                        'cloudstack_job' => $cloudstackJob->toArray(),
                    ],
                ]
            );

            $deployment->last_action_status = VpsActionStatus::RESETTING_CREDENTIALS;
            $deployment->save();

            $this->bus->dispatch(new ResetPasswordJob(
                $deployment,
                $cloudstackJob
            ));

            // Job has been dispatched so subscription status is pending until job completes or fails
            $subscription->technical_status = TechnicalStatus::PENDING->value;
            $subscription->save();
        } catch (ClientFactoryException | ClientException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function resetSshKey(VirtualMachineDeployment $deployment, string $newKeyName): bool
    {
        if ($deployment->cloudstack_id === null) {
            $logMessage = sprintf(
                'Trying to reset sshkey for VM deployment (%d) without a cloudstack ID.',
                $deployment->id,
            );

            $this->logger->warning(
                $logMessage,
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ]
            );

            return false;
        }

        try {
            $client = $this->clientFactory->create($deployment->managerDomainDeployment);

            $resetSshKeyResetJob = $client->resetSshKeyForVirtualMachine(
                vmId: $deployment->cloudstack_id,
                keyPair: $newKeyName
            );

            $cloudstackJob = $this->createCloudstackJob($resetSshKeyResetJob, $deployment, [
                'job_id' => $resetSshKeyResetJob->jobId,
            ]);

            $this->logger->info(
                sprintf(
                    'Cloudstack resetting VMs [%s] ssh key with job ID %s for subscription %s.',
                    $deployment->cloudstack_id,
                    $cloudstackJob->job_id,
                    $deployment->subscription->uuid,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                    LoggingContextKeys::META => [
                        'cloudstack_job' => $cloudstackJob->toArray(),
                    ],
                ]
            );

            $deployment->last_action_status = VpsActionStatus::RESETTING_SSH_KEY;
            $deployment->save();

            $this->bus->dispatch(new ResetSshKeyForVirtualMachineJob(
                $deployment,
                $cloudstackJob
            ));

            // Job has been dispatched so subscription status is pending until job completes or fails
            $deployment->subscription->technical_status = TechnicalStatus::PENDING->value;
            $deployment->subscription->save();
        } catch (ClientFactoryException | CloudstackException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }

    /*
     * @throws CloudstackNotFoundException | AdminClientFactoryException | CloudstackException | ClientException
     */
    public function getConsole(VirtualMachineDeployment $deployment): string
    {
        if ($deployment->cloudstack_id === null) {
            throw CloudstackNotFoundException::vmNotFound($deployment->subscription->uuid);
        }

        $adminClient = $this->adminClientFactory->create($deployment->managerDomainDeployment->environment);

        $consoleEndpoint = $adminClient->getConsoleEndpoint($deployment->cloudstack_id);
        if ($consoleEndpoint->success === false || $consoleEndpoint->url === null) {
            throw new CloudstackException(
                sprintf(
                    'Failed to get console URL for VM %s: %s',
                    $deployment->id,
                    $consoleEndpoint->details ?? 'Unknown error'
                )
            );
        }

        return $consoleEndpoint->url;
    }

    /**
     * @param array<string, string|null> $extraFields
     */
    protected function createCloudstackJob(
        AsynchronousCloudstackResponse $asyncResponse,
        VirtualMachineDeployment $deployment,
        array $extraFields = []
    ): CloudstackJob {
        $jobData = $this->serializer->normalize($asyncResponse);
        Assert::isArray($jobData);

        /** @var array<string,mixed> $data */
        $data = $jobData;
        $data['vm_deployment_id'] = $deployment->id;
        $data = array_merge($data, $extraFields);

        return CloudstackJob::create($data);
    }

    private function bindAndResetSshKey(CloudstackJob $jobModel, VirtualMachineDeployment $deployment): void
    {
        $sshUuid = $jobModel->ssh_key_uuid ?? throw new RuntimeException("No ssh_key_uuid on job {$jobModel->id}");
        $cloudName = $this->linkSshKeyAction->execute(
            $sshUuid,
            $deployment->id,
            $deployment->managerDomainDeployment
        );
        $this->resetSshKey($deployment, $cloudName);
    }
}
