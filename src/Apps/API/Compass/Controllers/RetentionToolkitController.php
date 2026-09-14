<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use RuntimeException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Requests\RetentionOfferRequest;
use Waterfront\Apps\API\Compass\Support\RetentionToolkitResponseMapper;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Authentication\AuthenticationManager;

readonly class RetentionToolkitController
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private ProductRepository $productRepository,
        private RetentionToolkitService $retentionToolkitService,
        private RetentionToolkitResponseMapper $responseMapper,
        private AuthenticationManager $authenticationManager,
    ) {
    }

    #[RequirePermission(Permissions::APPLY_RETENTION_OFFERS, SchemaId::EMPLOYEE)]
    public function calculate(RetentionOfferRequest $request, Customer $customer): ResourceCollection|JsonResponse
    {
        try {
            $retentionOfferDto = $this->getDtoFromRequest($request, $customer);

            $result = $this->retentionToolkitService->calculate($retentionOfferDto);
        } catch (RuntimeException $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            return new JsonResponse([
                'message' => $exception->getMessage(),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->responseMapper->fromResults($result);
    }

    #[RequirePermission(Permissions::APPLY_RETENTION_OFFERS, SchemaId::EMPLOYEE)]
    public function apply(RetentionOfferRequest $request, Customer $customer): ResourceCollection|JsonResponse
    {
        try {
            $retentionOfferDto = $this->getDtoFromRequest($request, $customer);
            $employee = $this->authenticationManager->getAuthenticatedEmployee();

            $result = $this->retentionToolkitService->apply(
                request: $retentionOfferDto,
                createdByMetadata: new IdentityMetadataDTO(
                    uuid: $employee->getAuthIdentifier(),
                    email: $employee->identitySchema->traits->email ?? '',
                ),
            );
        } catch (RuntimeException|AuthenticationException $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            return new JsonResponse([
                'message' => $exception->getMessage(),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->responseMapper->fromResults($result);
    }

    private function getDtoFromRequest(RetentionOfferRequest $request, Customer $customer): RetentionOfferRequestDTO
    {
        $items = [];
        foreach ($request->array('items') as $item) {
            $subscription = $this->subscriptionRepository->getByUuid($item['subscriptionUuid']);
            $targetProduct = null;

            if ($subscription === null) {
                throw new RuntimeException(sprintf('Subscription not found for UUID: %s', $item['subscriptionUuid']));
            }

            if ($subscription->customer_id !== $customer->id) {
                throw new RuntimeException(sprintf(
                    'Subscription %s does not belong to customer: %s',
                    $subscription->uuid,
                    $customer->uuid,
                ));
            }

            if (($item['targetProductUuid'] ?? null) !== null) {
                $targetProduct = $this->productRepository->findProductByUuid($item['targetProductUuid']);
            }

            $items[] = new RetentionOfferItemDTO(
                subscription: $subscription,
                selectedAction: SelectedAction::from($item['selectedAction']),
                executionDate: ExecutionDate::from($item['executionDate']),
                contractPeriod: $item['contractPeriod'],
                billingPeriod: $item['billingPeriod'],
                targetProduct: $targetProduct,
                cancelReason: SubscriptionCancelReason::tryFrom($item['cancelReason'] ?? ''),
                cancelReasonOther: $item['cancelReasonOther'] ?? null,
            );
        }

        return new RetentionOfferRequestDTO(
            customer: $customer,
            customerType: $request->customerType(),
            puzzelTicketId: (string) $request->string('puzzelTicketId'),
            items: $items,
        );
    }
}
