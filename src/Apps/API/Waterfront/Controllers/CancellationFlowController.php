<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Waterfront\Apps\API\Waterfront\Policies\CancellationFlowPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Subscription\CancellationFlowRequest;
use Waterfront\Domain\Subscriptions\Enums\CancellationActionPerformedType;
use Waterfront\Domain\Subscriptions\Enums\CancellationStepType;
use Waterfront\Domain\Subscriptions\Models\CancellationFlow;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationFlowService;

class CancellationFlowController
{
    public function __construct(
        private readonly CancellationFlowService $cancellationFlowService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CancellationFlowPolicy $cancellationFlowPolicy,
    ) {
    }

    public function create(CancellationFlowRequest $request): JsonResponse
    {
        if (! $this->cancellationFlowPolicy->validatePermissions()) {
            throw new AuthorizationException();
        }

        $ip = $request->ip();
        $subscriptions = $this->subscriptionRepository->getSubscriptionsByUuid($request->subscriptionUuids);

        if (
            ! $this->cancellationFlowPolicy->validateOwnership($subscriptions)
            || ! $this->cancellationFlowPolicy->validateAdminstrativeState($subscriptions)
        ) {
            throw new AuthorizationException();
        }

        $subscriptions->loadMissing('product.productGroup');

        assert(is_string($ip));
        $flow = $this->cancellationFlowService->start($subscriptions, $ip);
        $reasons = $this->cancellationFlowService->getReasons();
        $statistics = $this->cancellationFlowService->getStatistics($subscriptions);
        $offers = $this->cancellationFlowService->getOffers($subscriptions);

        /** @var int[] $contractPeriods */
        $contractPeriods = array_unique(array_merge([36, 24], $subscriptions->pluck('contract_period')->toArray()));
        /** @var int[] $billingPeriods */
        $billingPeriods = array_unique(array_merge([
            36,
            24,
            12,
            1,
        ], $subscriptions->pluck('billing_period')->toArray()));

        $prices = $this->cancellationFlowService->getSubscriptionPrices(
            $subscriptions,
            $contractPeriods,
            $billingPeriods,
        );
        // Format prices as array so we don't double encode to json
        $prices = array_map(fn (ResourceCollection $prices) => $prices->toArray($request), $prices);

        $payload = [
            'cancellationReasons' => $reasons,
            'subscriptionStatistics' => $statistics,
            'offers' => $offers,
            'prices' => $prices,
        ];

        $responseData = ['data' => null, 'action' => CancellationActionPerformedType::STARTED->value];
        $stepData = json_encode($payload);
        $responseData = json_encode($responseData);
        assert(is_string($stepData));
        assert(is_string($responseData));

        $this->cancellationFlowService->processCancellationSteps(
            $flow,
            CancellationStepType::START,
            $stepData,
            $responseData,
            CancellationActionPerformedType::STARTED,
        );

        return new JsonResponse([
            'id' => $flow->id,
            ...$payload,
        ]);
    }

    public function processStep(Request $request, CancellationFlow $cancellationFlow): JsonResponse
    {
        if (! $this->cancellationFlowPolicy->validatePermissions()) {
            throw new AuthorizationException();
        }

        $stepType = $request->string('stepType')->toString();
        $stepData = $request->input('stepData');
        $responseData = $request->input('responseData');

        $this->cancellationFlowService->validateStepData($request, CancellationStepType::from($stepType));

        assert(is_array($responseData));
        assert(is_string($responseData['action']));
        $action = CancellationActionPerformedType::from($responseData['action']);

        $stepData = json_encode($stepData);
        $responseData = json_encode($responseData);
        assert(is_string($stepData));
        assert(is_string($responseData));

        $this->cancellationFlowService->processCancellationSteps(
            $cancellationFlow,
            CancellationStepType::from($stepType),
            $stepData,
            $responseData,
            $action,
        );

        return new JsonResponse(['message' => 'ok']);
    }
}
