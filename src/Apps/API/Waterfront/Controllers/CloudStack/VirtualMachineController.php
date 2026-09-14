<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers\CloudStack;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\CloudStack\CustomNameRequest;
use Waterfront\Apps\API\Waterfront\Requests\CloudStack\ReinstallVpsRequest;
use Waterfront\Apps\API\Waterfront\Requests\CloudStack\ResetPasswordRequest;
use Waterfront\Apps\API\Waterfront\Requests\CloudStack\ResetSshKeyRequest;
use Waterfront\Apps\API\Waterfront\Requests\CloudStack\StateRequest;
use Waterfront\Apps\API\Waterfront\Resources\CloudStack\AvailableReinstallOptionsResource;
use Waterfront\Apps\API\Waterfront\Resources\CloudStack\VirtualMachineResource;
use Waterfront\Apps\API\Waterfront\Resources\CloudStack\VirtualMachineTechnicalDeploymentResource;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Actions\ReinstallVirtualMachineAction;
use Waterfront\Domain\VPS\Actions\ResetVirtualMachineSshKeyAction;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class VirtualMachineController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CustomerPolicy $customerPolicy,
        private readonly VirtualMachineServiceInterface $virtualMachineService,
        private readonly VirtualMachineDeploymentRepositoryInterface $virtualMachineDeploymentRepository,
        private readonly TranslatorInterface $translator,
        private readonly ResetVirtualMachineSshKeyAction $resetSshKeyAction,
        private readonly SshKeyRepository $sshKeyRepository,
        private readonly VirtualMachineTechnicalDeploymentResource $virtualMachineDeploymentResource,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly ReinstallVirtualMachineAction $reinstallAction,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string|mixed>
     */
    public function getDeployment(VirtualMachineDeployment $virtualMachineDeployment): array
    {
        $virtualMachine = $this->virtualMachineService->findByDeployment($virtualMachineDeployment);
        $this->subscriptionPolicy->assertCanManageVirtualMachine($virtualMachineDeployment->subscription);

        return $this->virtualMachineDeploymentResource->toArray(
            $virtualMachineDeployment,
            $virtualMachine->nic[0]->ipAddress ?? '',
            $virtualMachine->nic[0]->ip6Address ?? '',
            $virtualMachine->state->value ?? CloudstackMachineState::UNKNOWN->value,
        );
    }

    /**
     * @throws AuthorizationException
     *
     * @return AnonymousResourceCollection<VirtualMachineResource>
     */
    public function index(): AnonymousResourceCollection
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageVPS();
        $virtualMachineSubscriptions = $customer
            ->subscriptions()
            ->with(['cloudStackVirtualMachineDeployment', 'cloudStackVirtualMachineDeployment.managerDomainDeployment'])
            ->whereHas('product.productGroup', function (Builder $q): void {
                $q->where('slug', ProductGroupType::VPS);
                $q->orWhere('slug', ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE);
            })
            ->get();

        $vmCollection = $this->groupVirtualMachinesByManagerDomain($virtualMachineSubscriptions);

        $virtualMachineResources = [];

        foreach ($vmCollection as $vmCollectionItem) {
            $managerDomainDeployment = $vmCollectionItem['manager_domain_deployment'];

            // If we don't have a manager domain we can't fetch the state, but we still want to show the VPS in the list
            if ($managerDomainDeployment === null) {
                foreach ($vmCollectionItem['virtual_machine_subscriptions'] as $virtual_machine_subscription) {
                    $virtualMachineResources[] = VirtualMachineResource::make($virtual_machine_subscription);
                }

                continue;
            }

            $virtualMachines = new Collection($this->virtualMachineService->findVirtualMachines(
                $managerDomainDeployment,
            ));

            foreach ($vmCollectionItem['virtual_machine_subscriptions'] as $virtualMachineSubscription) {
                $virtualMachine = $virtualMachines->firstWhere(
                    'id',
                    '=',
                    $virtualMachineSubscription->cloudStackVirtualMachineDeployment?->cloudstack_id,
                );

                if ($virtualMachine === null) {
                    $virtualMachineResources[] = VirtualMachineResource::make($virtualMachineSubscription);
                    continue;
                }

                $resource = VirtualMachineResource::make($virtualMachineSubscription)->additional([
                    'vps_status' => $virtualMachine->state->value,
                    'ipAddress' => $virtualMachine->nic[0]->ipAddress,
                    'ip6Address' => $virtualMachine->nic[0]->ip6Address,
                ]);

                $virtualMachineResources[] = $resource;
            }
        }

        return VirtualMachineResource::collection(
            $virtualMachineResources,
        );
    }

    /** @throws AuthenticationException */
    public function state(StateRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $state = (string) $request->string('state');

        try {
            $deployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );

            $result = $this->virtualMachineService->handleByStateAndDeployment($state, $deployment);
        } catch (VirtualMachineNotFoundException) {
            return new JsonResponse(
                [
                    'error' => $this->translator->translate('vps.not-found'),
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($result === false) {
            return new JsonResponse(
                [
                    'error' => sprintf('Failed to %s the VPS.', $state),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(['status' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function getAvailableReinstallOptions(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        try {
            $osSubscriptionChild = $this->virtualMachineDeploymentRepository->getOsSubscriptionChildFromSubscriptionUuid($subscription->uuid);
        } catch (VirtualMachineNotFoundException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('vps.not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $options = $this->virtualMachineService->getAvailableReinstallOptions($osSubscriptionChild);

        $resourceCollection = AvailableReinstallOptionsResource::collection($options);

        return $resourceCollection->response()->setStatusCode(Response::HTTP_OK);
    }

    public function reinstall(ReinstallVpsRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        try {
            $deployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );
        } catch (VirtualMachineNotFoundException) {
            return new JsonResponse(
                ['message' => $this->translator->translate('vps.not-found'), 'errors' => []],
                Response::HTTP_NOT_FOUND,
            );
        }

        /** @var string $osUuid */
        $osUuid = $request->input('os_product_uuid');
        /** @var string|null $sshKeyUuid */
        $sshKeyUuid = $request->input('ssh_key_uuid') ?? null;

        try {
            $osChildSubscription = $this->virtualMachineDeploymentRepository->getOsSubscriptionChildFromSubscriptionUuid($subscription->uuid);
            if ($osChildSubscription->product->productGroup->slug !== ProductGroupType::CLOUDSTACK_OS) {
                return new JsonResponse(
                    ['error' => $this->translator->translate('vps.invalid-os-subscription')],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $ok = $this->reinstallAction->execute(
                $osChildSubscription,
                $deployment,
                $osUuid,
                $sshKeyUuid,
            );
        } catch (InvalidArgumentException|ClientException $exception) {
            $this->logger->error('Error during VM reinstall', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            $ok = false;
        }

        if (! $ok) {
            return new JsonResponse(
                ['error' => $this->translator->translate('vps.reinstall-failed')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(['status' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function resetPassword(ResetPasswordRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        $password = (string) $request->string('password');
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        try {
            $deployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );
        } catch (VirtualMachineNotFoundException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('vps.not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $virtualMachine = $this->virtualMachineService->findByDeployment($deployment);
        if (! $virtualMachine instanceof VirtualMachine || $virtualMachine->state !== CloudstackMachineState::STOPPED) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('vps.not-stopped'),
                    'errors' => [],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $result = $this->virtualMachineService->resetPassword($deployment, $password);

        if ($result === false) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('vps.reset-password-failed'),
                    'errors' => [],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(['status' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function resetSshKey(ResetSshKeyRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $sshKeyUuid = (string) $request->string('ssh_uuid');

        try {
            $virtualMachineDeployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );

            $newSshKey = $this->sshKeyRepository->findByCustomerAndUuid(
                customer: $customer,
                uuid: $sshKeyUuid,
            );

            return (
                $this->resetSshKeyAction->execute(
                    virtualMachineDeployment: $virtualMachineDeployment,
                    newSshKey: $newSshKey,
                )
                    ? new JsonResponse(status: Response::HTTP_NO_CONTENT)
                    : new JsonResponse(
                        ['message' => $this->translator->translate('ssh-key.general-reset.error')],
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                    )
            );
        } catch (VirtualMachineNotFoundException) {
            return new JsonResponse(
                ['message' => $this->translator->translate('ssh-key.notfound-reset.error')],
                Response::HTTP_NOT_FOUND,
            );
        } catch (ClientFactoryException) {
            return new JsonResponse(
                ['message' => $this->translator->translate('ssh-key.technical-reset.error')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function customName(CustomNameRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $customName = (string) $request->string('custom_name');

        try {
            $virtualMachineDeployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );

            $this->virtualMachineDeploymentRepository->updateCustomName($virtualMachineDeployment, $customName);

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('status.success'),
                    'errors' => [],
                ],
            );
        } catch (VirtualMachineNotFoundException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('vps.not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws AdminClientFactoryException
     * @throws CloudstackException
     * @throws ClientException
     */
    public function getConsoleUrl(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageVirtualMachine($subscription);
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        try {
            $virtualMachineDeployment = $this->virtualMachineDeploymentRepository->findBySubscriptionUuid(
                $subscription->uuid,
                $customer->id,
            );

            $consoleUrl = $this->virtualMachineService->getConsole($virtualMachineDeployment);

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('status.success'),
                    'console_url' => $consoleUrl,
                    'errors' => [],
                ],
            );
        } catch (VirtualMachineNotFoundException|CloudstackNotFoundException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('vps.not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }
    }

    /**
     * @param Collection<int, Subscription> $virtualMachineSubscriptions
     *
     * @return Collection<int, array{manager_domain_deployment: null|ManagerDomainDeployment, virtual_machine_subscriptions: array<int, Subscription>}>
     */
    private function groupVirtualMachinesByManagerDomain(Collection $virtualMachineSubscriptions): Collection
    {
        return $virtualMachineSubscriptions
            ->groupBy(
                fn (Subscription $subscription) => (
                    $subscription->cloudStackVirtualMachineDeployment->managerDomainDeployment->id
                    ?? 'unknown-manager-domain'
                ),
            )
            ->map(fn ($group) => [
                'manager_domain_deployment' =>
                    $group->first()?->cloudStackVirtualMachineDeployment?->managerDomainDeployment,
                'virtual_machine_subscriptions' => $group->all(),
            ])
            ->values();
    }
}
