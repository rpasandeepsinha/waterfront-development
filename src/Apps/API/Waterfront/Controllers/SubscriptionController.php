<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Subscription\CancelChildSubscriptionsRequest;
use Waterfront\Apps\API\Waterfront\Requests\Subscription\CancelRequest;
use Waterfront\Apps\API\Waterfront\Resources\DomainAndCustomerRelationSubscriptionResource;
use Waterfront\Apps\API\Waterfront\Resources\SubscriptionOverviewResource;
use Waterfront\Apps\API\Waterfront\Resources\SubscriptionPresenter;
use Waterfront\Apps\API\Waterfront\Resources\SubscriptionsToResourceConverter;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Actions\DowngradeSubscriptionOnCancelAction;
use Waterfront\Domain\Subscriptions\DTO\RequestCancellationDTO;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\AmountException;
use Waterfront\Domain\Subscriptions\Exceptions\CancelNotRevertedException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionAddonService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Domain\Subscriptions\SubscriptionResource;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class SubscriptionController
{
    public function __construct(
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly SubscriptionService $subscriptionService,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly AuthenticationManager $authenticationManager,
        private readonly SubscriptionsToResourceConverter $subscriptionToResourceConverter,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly DowngradeSubscriptionOnCancelAction $downgradeSubscriptionOnCancelAction,
        private readonly SubscriptionPresenter $subscriptionPresenter,
        private readonly DomainAndCustomerRelationSubscriptionResource $domainAndCustomerRelationSubscriptionResource,
        private readonly SubscriptionAddonService $subscriptionAddonAdditionService,
        private readonly CancellationService $cancellationService,
    ) {
    }

    public function getSubscriptionsEligibleForServicePlus(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $collection = $this->subscriptionRepository->findAllSubscriptionsInHostingGroupWithoutServicePlusProductSpec($customer->id);

        return new JsonResponse(['data' => $this->subscriptionPresenter->collectionToArray($collection)]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanAccess($subscription);

        $subscription->loadMissing(['children', 'children.product', 'product', 'product.productGroup', 'labels', 'mutations']);

        return new JsonResponse(['data' => $this->subscriptionPresenter->toArray($subscription)]);
    }

    /**
     * @throws AuthorizationException|AuthenticationException
     */
    public function indexOverview(Request $request): JsonResponse
    {
        $groupFilter = $request->input('filter');
        $filter = null;
        if ($groupFilter !== null) {
            Assert::string($groupFilter);
            $filter = ProductGroupType::from($groupFilter);
        }

        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $subscriptions = $this->subscriptionRepository
            ->getAllSubscriptionsForCustomerBasedOnGroupFilter($customer, $filter);

        return new JsonResponse(['data' => SubscriptionOverviewResource::collection($subscriptions)]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function index(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $subscriptions = $this->subscriptionRepository->getActiveSubscriptionOverview($customer);

        return new JsonResponse([ 'data' => $this->subscriptionToResourceConverter->toArray($subscriptions)]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function cancel(CancelRequest $request): AnonymousResourceCollection
    {
        $subscriptionsRequest = $request->subscriptions;
        $subscriptions = $this->subscriptionService->getSubscriptionsQuery()
            ->whereIn('uuid', Arr::pluck($subscriptionsRequest, 'uuid'))
            ->get();

        foreach ($subscriptionsRequest as $cancellationData) {
            if ($cancellationData['cancel_type'] === null || $cancellationData['cancel'] === false) {
                continue;
            }

            Assert::string($cancellationData['uuid']);
            Assert::boolean($cancellationData['cancel']);
            Assert::string($cancellationData['cancel_type']);
            Assert::string($cancellationData['cancel_reason']);

            $cancelType = SubscriptionCancelType::from($cancellationData['cancel_type']);

            $cancellation = new RequestCancellationDTO(
                $cancellationData['uuid'],
                $cancellationData['cancel'],
                $cancelType,
                SubscriptionCancelReason::from($cancellationData['cancel_reason']),
            );

            $subscription = $subscriptions->where('uuid', $cancellation->uuid)->first();
            if (! $subscription instanceof Subscription) {
                $this->logger->warning(
                    'Tried to cancel subscription with non existing uuid: {subscription.uuid}',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $cancellation->uuid,
                    ]
                );
                continue;
            }
            $this->subscriptionPolicy->assertCanCancel($subscription);

            if ($cancellation->cancelType === SubscriptionCancelType::CANCEL_DOWNGRADE) {
                $this->downgradeSubscriptionOnCancelAction->execute($subscription);
                continue;
            }

            $this->cancellationService->cancel($subscription, $cancellation->cancelType, $cancellation->cancelReason);
        }

        return SubscriptionResource::collection($subscriptions->fresh());
    }

    public function revertCancel(
        Subscription $subscription,
    ): JsonResponse {
        $this->subscriptionPolicy->assertCanAccess($subscription);

        try {
            $this->cancellationService->revertCancel($subscription);
        } catch (CancelNotRevertedException $cancelNotRevertedException) {
            Log::error(
                sprintf(
                    'Revert cancel has failed for subscription UUID "%s". "%s"',
                    $subscription->uuid,
                    $cancelNotRevertedException,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::CUSTOMER_ID => $subscription->customer->id,
                    LoggingContextKeys::EXCEPTION => $cancelNotRevertedException,
                ]
            );
            return new JsonResponse(
                ['message' => $this->translator->translate('services.revert-cancel-subscription.failed')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new JsonResponse(['message' => $this->translator->translate('services.revert-cancel-subscription.successful')]);
    }

    public function cancelChildSubscription(
        CancelChildSubscriptionsRequest $request,
    ): JsonResponse {
        try {
            $this->cancellationService->cancelChildSubscriptions(
                $request->parent,
                $request->amount
            );
        } catch (AmountException $amountException) {
            $this->logger->warning(
                'Cancel Child subscription failed',
                [
                    LoggingContextKeys::EXCEPTION => $amountException,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $request->parent,
                    LoggingContextKeys::META => [
                        'amount_requested' => $request->amount,
                    ],
                ]
            );
            return new JsonResponse([
                'message' => $amountException->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => $this->translator->translate('services.cancel-child-subscription.successful')]);
    }

    public function getPotentialUpgrades(
        Subscription $subscription,
        SubscriptionChangeService $subscriptionChangeService
    ): JsonResponse {
        try {
            $addons = $this->subscriptionAddonAdditionService
                ->getPotentialAddons($subscription)
                ->values()
                ->toArray();
        } catch (InvalidArgumentException) {
            $addons = [];
        }

        return new JsonResponse([
            'upgrades' =>
                $subscriptionChangeService
                    ->getPotentialChanges(ProductChangeType::UPGRADE, $subscription)
                    ->values()
                    ->toArray(),
            'addons' => $addons,
        ]);
    }

    public function getDomainAndCustomerRelationSubscriptions(Subscription $subscription): JsonResponse
    {
        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = Subscription::query()
            ->with(['children', 'children.product', 'product', 'product.productGroup'])
            ->where('customer_id', $subscription->customer_id)
            ->where('domain', $subscription->domain)
            ->whereNot('uuid', $subscription->uuid)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->get();

        return new JsonResponse(['data' => $this->domainAndCustomerRelationSubscriptionResource->toArray($subscriptions)]);
    }

    public function getPotentialDowngrades(Subscription $subscription, SubscriptionChangeService $subscriptionChangeService): JsonResponse
    {
        return new JsonResponse([
            'downgrades' => $subscriptionChangeService
                ->getPotentialChanges(ProductChangeType::DOWNGRADE, $subscription)
                ->values()
                ->toArray(),
        ]);
    }
}
