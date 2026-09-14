<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Actions\ResolveAndLinkSshKeyAction;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\Vps;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Exceptions\ManagerDomainException;
use Waterfront\Domain\VPS\Exceptions\NoAvailableNetworkFoundException;
use Waterfront\Domain\VPS\Exceptions\NoCloudstackTemplateFoundFromProduct;
use Waterfront\Domain\VPS\Exceptions\NonVpsOsProductException;
use Waterfront\Domain\VPS\Exceptions\SshKeyNotFoundException;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Jobs\DeployVirtualMachineJob;
use Waterfront\Domain\VPS\Mailer\MailCloudstackManagerVpsDetails;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\CloudstackJobRepository;
use Waterfront\Domain\VPS\Repositories\EnvironmentProductRepository;
use Waterfront\Domain\VPS\Repositories\EnvironmentRepository;
use Waterfront\Domain\VPS\Repositories\ManagerDomainDeploymentRepository;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class VpsService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly VirtualMachineDeploymentRepositoryInterface $virtualMachineDeploymentRepository,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly ManagerDomainDeploymentRepository $managerDomainDeploymentRepository,
        private readonly ManagerDomainService $managerDomainService,
        private readonly ClientFactoryInterface $clientFactory,
        private readonly VpsTemplateService $vpsTemplateService,
        private readonly EnvironmentProductRepository $environmentProductRepository,
        private readonly ResolveAndLinkSshKeyAction $resolveAndLinkSshKeyAction,
        private readonly Serializer $serializer,
        private readonly Dispatcher $bus,
        private readonly MailerInterface $mailer,
        private readonly CloudstackJobRepository $cloudstackJobRepository,
        private readonly VirtualMachineServiceInterface $virtualMachineService,
    ) {
    }

    public function create(
        Subscription $subscription,
        ?string $sshKeyUuid,
    ): void {
        $customer = $subscription->customer;
        $product = $subscription->product;

        try {
            $osProduct = $this->virtualMachineDeploymentRepository->getOsSubscriptionChildFromSubscriptionUuid(
                subscriptionUuid: $subscription->uuid,
            )->product;

            $environment = $this->resolveEnvironment(
                customerId: $customer->id,
                productId: $product->id,
                osProduct: $osProduct,
                subscriptionUuid: $subscription->uuid,
            );

            $managerDomainDeployment = $this->findOrCreateManagerDomain(
                environment: $environment,
                customer: $customer,
            );

            $vmDeployment = $this->findOrCreateVmDeployment(
                subscriptionUuid: $subscription->uuid,
                managerDomainDeployment: $managerDomainDeployment,
            );

            $isAlreadyProvisioned = $this->isAlreadyProvisioned($vmDeployment);
            if ($isAlreadyProvisioned) {
                $this->logger->info('Skipping VPS deploy: deployment already linked to CloudStack VM.', [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::PROVISIONING_ID => $vmDeployment->id,
                ]);

                $this->setTechnicalStatusSubscription($subscription, TechnicalStatus::OK);

                return;
            }

            if ($this->cloudstackJobRepository->hasActiveJobForVmDeployment($vmDeployment->id)) {
                $this->logger->info('Skipping VPS deploy: active CloudStack job exists.', [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::PROVISIONING_ID => $vmDeployment->id,
                ]);

                $this->setTechnicalStatusSubscription($subscription, TechnicalStatus::PENDING);

                return;
            }

            if ($vmDeployment->cloudstack_id !== null) {
                $this->clearCloudstackId(
                    deployment: $vmDeployment,
                    message: 'Create: CloudStack VM not found; clearing cloudstack_id before deploy.',
                );
                $vmDeployment->refresh();
            }

            $this->logger->debug(
                'Creating VPS',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::META => [
                        'manager_domain_deployment_id' => $managerDomainDeployment->id,
                        'manager_domain_deployment_domain_name' => $managerDomainDeployment->domain_name,
                        'manager_domain_deployment_account' => $managerDomainDeployment->account,
                    ],
                ],
            );

            $client = $this->clientFactory->create($managerDomainDeployment);

            $domainId = $managerDomainDeployment->domain_id;
            if ($domainId === null) {
                throw CloudstackNotFoundException::domainIdNotFound($managerDomainDeployment->domain_name);
            }

            $this->deployToCloudstack(
                client: $client,
                environment: $environment,
                managerDomainDeployment: $managerDomainDeployment,
                vmDeployment: $vmDeployment,
                domainId: $domainId,
                customer: $customer,
                product: $product,
                osProduct: $osProduct,
                sshKeyUuid: $sshKeyUuid,
            );

            $this->setTechnicalStatusSubscription(
                subscription: $subscription,
                status: TechnicalStatus::PENDING,
            );
        } catch (
            CloudstackException|ClientException|ManagerDomainException|SshKeyNotFoundException|NonVpsOsProductException|NoAvailableNetworkFoundException|NoCloudstackTemplateFoundFromProduct|AdminClientFactoryException|CloudstackNotFoundException $exception
        ) {
            $this->logger->error(
                sprintf(
                    'Deploy virtual machine with subscription UUID %s failed',
                    $subscription->uuid,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $this->setTechnicalStatusSubscription(
                subscription: $subscription,
                status: TechnicalStatus::ERROR,
            );

            try {
                $vmDeployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                    $subscription->uuid,
                    $customer->id,
                );

                $vmDeployment->last_result_received = CarbonImmutable::now();
                $vmDeployment->last_result = $exception->getMessage();
                $vmDeployment->save();
            } catch (VirtualMachineNotFoundException) {
                $this->logger->warning('VPS deploy failed before VM deployment was created; skipping deployment error update.', [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                ]);
            }

            throw $exception;
        }
    }

    public function retry(Subscription $subscription, ?string $sshKeyUuid, bool $deleteVmFirst): void
    {
        $this->logger->info('VPS retry requested.', [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
            LoggingContextKeys::META => [
                'ssh_key_uuid' => $sshKeyUuid,
                'delete_vm_first' => $deleteVmFirst,
            ],
        ]);

        $customer = $subscription->customer;

        try {
            $deployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );
        } catch (VirtualMachineNotFoundException) {
            $this->logger->debug(
                'No existing VM deployment found, creating new deployment.',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ],
            );

            $environment = $this->environmentRepository->getPreferredEnvironment();
            if ($environment instanceof Environment) {
                $managerDomainDeployment = $this->managerDomainDeploymentRepository->getManagerDomainDeploymentByEnvironment(
                    customerId: $subscription->customer_id,
                    environmentId: $environment->id,
                );

                if (
                    $managerDomainDeployment instanceof ManagerDomainDeployment
                    && $managerDomainDeployment->virtualMachineDeployments->count() === 0
                ) {
                    $this->logger->debug('Deleting existing manager domain deployment with no VM deployments.', [
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                        LoggingContextKeys::META => [
                            'manager_domain_deployment_id' => $managerDomainDeployment->id,
                        ],
                    ]);

                    $this->managerDomainService->deleteDomain($managerDomainDeployment);
                    $managerDomainDeployment->delete();
                }
            }

            $this->create($subscription, $sshKeyUuid);

            return;
        }

        if ($this->cloudstackJobRepository->hasActiveJobForVmDeployment($deployment->id)) {
            $this->logger->info('Retry skipped: active CloudStack job exists for deployment.', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
            ]);

            $this->setTechnicalStatusSubscription($subscription, TechnicalStatus::PENDING);

            return;
        }

        $vm = $this->virtualMachineService->findByDeployment($deployment);
        $vmExists = $vm !== null;

        $this->logger->info('retrying VPS.', [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PROVISIONING_ID => $deployment->id,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
            LoggingContextKeys::META => [
                'vm_exists' => $vmExists,
                'cloudstack_id' => $deployment->cloudstack_id,
            ],
        ]);

        if ($vmExists) {
            if ($deleteVmFirst) {
                $this->setTechnicalStatusSubscription($subscription, TechnicalStatus::DELETING);

                $deployment->last_action_status = VpsActionStatus::DELETING_FOR_REDEPLOY;
                $deployment->last_result_received = CarbonImmutable::now();
                $deployment->last_result = 'Retry requested: delete VM first, then redeploy.';
                $deployment->save();

                $this->virtualMachineService->destroy($deployment, $sshKeyUuid);

                return;
            }

            $osProduct =
                $this->virtualMachineDeploymentRepository->getOsSubscriptionChildFromSubscriptionUuid(subscriptionUuid: $subscription->uuid)->product;

            $this->setTechnicalStatusSubscription($subscription, TechnicalStatus::PENDING);

            $this->virtualMachineService->reinstall(
                deployment: $deployment,
                newOs: $osProduct,
                sshKeyUuid: $sshKeyUuid,
            );

            return;
        }

        $this->setTechnicalStatusSubscription($subscription, TechnicalStatus::PENDING);

        $deployment->sshKeys()->detach();
        $deployment->last_action_status = null;
        $deployment->save();

        $this->clearCloudstackId(
            deployment: $deployment,
            message: 'Retry: CloudStack VM not found; clearing cloudstack_id before redeploy.',
        );
        $this->create($subscription, $sshKeyUuid);
    }

    public function mailCustomerVmDetails(
        VirtualMachineDeployment $virtualMachineDeployment,
        VirtualMachine $vm,
    ): void {
        $nic = array_first($vm->nic);

        if ($nic === null) {
            $this->logger->warning(
                'Skipping VPS mail: VM has no NICs.',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $virtualMachineDeployment->subscription_uuid,
                    LoggingContextKeys::CUSTOMER_ID => $virtualMachineDeployment->subscription->customer->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ],
            );

            return;
        }

        $this->mailer->send(
            recipients: [$virtualMachineDeployment->subscription->customer],
            template: new MailCloudstackManagerVpsDetails(
                username: Vps::DEFAULT_USER->value,
                password: $vm->password,
                ipaddress: $nic->ipAddress,
                ip6address: $nic->ip6Address,
            ),
        );
    }

    private function clearCloudstackId(VirtualMachineDeployment $deployment, string $message): void
    {
        if ($deployment->cloudstack_id === null) {
            return;
        }

        $deployment->cloudstack_id = null;
        $deployment->last_result_received = CarbonImmutable::now();
        $deployment->last_result = $message;
        $deployment->save();
    }

    private function resolveEnvironment(
        string $subscriptionUuid,
        int $customerId,
        int $productId,
        Product $osProduct,
    ): Environment {
        $environment = $this->environmentRepository->getPreferredEnvironment();

        if ($environment instanceof Environment) {
            return $environment;
        }

        $this->logger->error(
            sprintf(
                'Cloudstack environment not found for OS product: [%d] %s with subscription UUID %s and product ID %d',
                $osProduct->id,
                $osProduct->slug,
                $subscriptionUuid,
                $productId,
            ),
            [
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::PRODUCT_ID => $productId,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::META => [
                    'os_product_id' => $osProduct->id,
                    'os_product_slug' => $osProduct->slug,
                ],
            ],
        );

        throw CloudstackNotFoundException::environmentNotFound($osProduct->id, $productId);
    }

    /**
     * @throws AdminClientFactoryException
     * @throws ClientException
     */
    private function findOrCreateManagerDomain(
        Environment $environment,
        Customer $customer,
    ): ManagerDomainDeployment {
        $existing = $this->managerDomainDeploymentRepository->getManagerDomainDeploymentByEnvironment(
            customerId: $customer->id,
            environmentId: $environment->id,
        );

        if ($existing instanceof ManagerDomainDeployment) {
            return $existing;
        }

        return $this->managerDomainService->create(environment: $environment, customer: $customer);
    }

    private function findOrCreateVmDeployment(
        string $subscriptionUuid,
        ManagerDomainDeployment $managerDomainDeployment,
    ): VirtualMachineDeployment {
        return $this->virtualMachineDeploymentRepository->firstOrCreateBySubscriptionUuid(
            subscriptionUuid: $subscriptionUuid,
            managerDomainDeployment: $managerDomainDeployment,
        );
    }

    private function isAlreadyProvisioned(VirtualMachineDeployment $deployment): bool
    {
        if ($deployment->cloudstack_id === null) {
            return false;
        }

        return $this->virtualMachineService->findByDeployment($deployment) !== null;
    }

    /**
     * @throws ClientException
     * @throws CloudstackException
     * @throws ManagerDomainException
     * @throws SshKeyNotFoundException
     * @throws NonVpsOsProductException
     * @throws NoAvailableNetworkFoundException
     * @throws NoCloudstackTemplateFoundFromProduct
     */
    private function deployToCloudstack(
        CloudStackClient $client,
        Environment $environment,
        ManagerDomainDeployment $managerDomainDeployment,
        VirtualMachineDeployment $vmDeployment,
        string $domainId,
        Customer $customer,
        Product $product,
        Product $osProduct,
        ?string $sshKeyUuid,
    ): void {
        $osTemplate = $this->vpsTemplateService->getTemplateByProduct(
            product: $osProduct,
            environment: $environment,
        );

        $networks = $client->listNetworks();
        $network = array_first($networks);

        if ($network === null) {
            throw new NoAvailableNetworkFoundException();
        }

        /**
         * For now hostname and fqdn are generated with the user account.
         * Will be changed in VPS v3.2.
         *
         * @see https://yh-jira.atlassian.net/browse/WATER-2901
         */
        $fqdn = $hostname = $managerDomainDeployment->account . '-' . uniqid();

        $createdJob = $client->deployVirtualMachine(
            serviceOfferingId: $this->environmentProductRepository->getComputeOfferingId(
                productId: $product->id,
                environmentId: $environment->id,
            ),
            osTemplateId: $osTemplate->id,
            zoneId: $client->listZones()->id,
            domainId: $domainId,
            account: $managerDomainDeployment->account,
            securityGroupId: $this->prepareSecurityGroup(
                cloudStackClient: $client,
                managerDomainDeployment: $managerDomainDeployment,
            ),
            displayName: $this->getDisplayName(
                productName: $product->name,
                osName: $osProduct->name,
                customer: $customer,
            ),
            hostname: $hostname,
            fqdn: $fqdn,
            keyPair: $sshKeyUuid !== null
                ? $this->resolveAndLinkSshKeyAction->execute(
                    sshKeyUuid: $sshKeyUuid,
                    vmDeploymentId: $vmDeployment->id,
                    managerDomainDeployment: $managerDomainDeployment,
                )
                : null,
            networkId: $network->id,
        );

        $cloudstackJob = $this->persistCloudstackJob(
            jobId: $createdJob->jobId,
            vmDeployment: $vmDeployment,
        );

        $this->logger->info(
            sprintf(
                'Cloudstack deploying VM with job ID %s for subscription %s.',
                $cloudstackJob->job_id,
                $vmDeployment->subscription_uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $vmDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::META => [
                    'cloudstack_job' => $cloudstackJob->toArray(),
                    'hostname' => $hostname,
                    'fqdn' => $fqdn,
                ],
            ],
        );

        $this->bus->dispatch(new DeployVirtualMachineJob(
            deployment: $vmDeployment,
            cloudstackJob: $cloudstackJob,
        ));
    }

    /**
     * @throws ManagerDomainException
     */
    private function prepareSecurityGroup(
        CloudStackClient $cloudStackClient,
        ManagerDomainDeployment $managerDomainDeployment,
    ): string {
        /**
         * Currently API calls to 'listSecurityGroups' in cloudstack are not working correctly.
         * Ideally we would like to get current security group and if it exists apply it.
         * But for now we add a unique id to prevent duplicates in cloudstack.
         *
         * @todo WATER-2826 https://yh-jira.atlassian.net/browse/WATER-2826
         */
        $securityGroupName = $managerDomainDeployment->account . uniqid();
        $account = $managerDomainDeployment->account;
        $domainId = $managerDomainDeployment->domain_id;

        if ($domainId === null) {
            throw new ManagerDomainException(
                sprintf(
                    'DomainId not set on ManagerDomain subscription with ID %s',
                    $managerDomainDeployment->id,
                ),
            );
        }

        $securityGroupId = $cloudStackClient->createSecurityGroup(
            account: $account,
            domainId: $domainId,
            name: $securityGroupName,
        );

        $cloudStackClient->authorizeSecurityGroupIngress(
            account: $account,
            domainId: $domainId,
            securityGroupId: $securityGroupId,
        );

        return $securityGroupId;
    }

    private function persistCloudstackJob(string $jobId, VirtualMachineDeployment $vmDeployment): CloudstackJob
    {
        $virtualMachineJob = new AsynchronousCloudstackResponse(jobId: $jobId);

        $jobArray = $this->serializer->normalize($virtualMachineJob, AsynchronousCloudstackResponse::class);
        Assert::isArray($jobArray);

        $jobArray['vm_deployment_id'] = $vmDeployment->id;

        return $this->cloudstackJobRepository->create($jobArray);
    }

    private function getDisplayName(string $productName, string $osName, Customer $customer): string
    {
        return sprintf(
            '%s - %s (%s %s (%s))',
            $osName,
            $productName,
            $customer->first_name,
            $customer->last_name,
            $customer->uuid,
        );
    }

    private function setTechnicalStatusSubscription(Subscription $subscription, TechnicalStatus $status): void
    {
        // TODO (WATER-5727): change this to use $deployment->subscription->technical_status or at a later stage to
        //  $deployment->technical_status once the technical status is moved to the deployment.
        //  This is now only done because at this stage there is no deployment yet, and that would be too big
        //  of a refactor at this stage.
        $subscription->update(['technical_status' => $status->value]);
        $subscription->children()->update(['technical_status' => $status->value]);
        $subscription->technical_status = $status->value;
    }
}
