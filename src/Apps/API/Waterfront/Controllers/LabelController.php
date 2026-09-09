<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Http\JsonResponse;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Policies\LabelPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Label\AttachSubscriptionRequest;
use Waterfront\Apps\API\Waterfront\Requests\Label\CreateLabelRequest;
use Waterfront\Apps\API\Waterfront\Requests\Label\DeleteLabelRequest;
use Waterfront\Apps\API\Waterfront\Requests\Label\DetachSubscriptionRequest;
use Waterfront\Apps\API\Waterfront\Resources\LabelResource;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Services\LabelService;
use Waterfront\Infra\Authentication\AuthenticationManager;

class LabelController
{
    public function __construct(
        private readonly LabelService $labelService,
        private readonly AuthenticationManager $authenticationManager,
        private readonly LabelPolicy $labelPolicy,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    public function getLabels(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageLabels();

        $labels = $this->labelService->getLabels($customer);

        return new JsonResponse(
            LabelResource::collection($labels)
        );
    }

    public function createLabels(CreateLabelRequest $request): JsonResponse
    {
        /** @var non-empty-array<non-empty-string> */
        $labelValues = $request->input('labels');
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageLabels();
        $this->labelService->createLabels($labelValues, $customer);

        return new JsonResponse();
    }

    public function deleteLabels(DeleteLabelRequest $request): JsonResponse
    {
        /** @var non-empty-array<non-empty-string> */
        $labelValues = $request->input('labels');

        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageLabels();
        $this->labelService->deleteLabels($labelValues, $customer);

        return new JsonResponse();
    }

    public function attachSubscriptions(AttachSubscriptionRequest $request, Label $label): JsonResponse
    {
        /** @var non-empty-array<positive-int> */
        $subscriptionIds = $request->input('subscription_ids');
        $this->labelPolicy->assertCanAccess($subscriptionIds);
        $this->labelPolicy->assertCanAccessLabel($label);

        $this->labelService->attachSubscriptions($label, $subscriptionIds);

        return new JsonResponse();
    }

    public function detachSubscriptions(DetachSubscriptionRequest $request, Label $label): JsonResponse
    {
        /** @var non-empty-array<positive-int> */
        $subscriptionIds = $request->input('subscription_ids');

        $this->labelPolicy->assertCanAccess($subscriptionIds);
        $this->labelPolicy->assertCanAccessLabel($label);

        $this->labelService->detachSubscriptions($label, $subscriptionIds);

        return new JsonResponse();
    }
}
