<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Apps\API\Waterfront\Requests\Puzzel\CreateCallbackRequest;
use Waterfront\Apps\API\Waterfront\Resources\ScheduledSupportCallTimeSlotsResource;
use Waterfront\Domain\Puzzel\Services\ScheduledSupportCallService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\PuzzelClient\Enums\Result;
use Waterfront\Infra\PuzzelClient\Exceptions\PuzzelResponseMissingRedirectException;
use Waterfront\Infra\Translation\Translator;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

readonly class ScheduledSupportCallController
{
    public function __construct(
        private AuthenticationManager $authenticationManager,
        private ScheduledSupportCallService $scheduledSupportCallService,
        private LoggerInterface $logger,
        private Translator $translator,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function store(
        CreateCallbackRequest $request,
    ): JsonResponse {
        $date = $request->date('date');
        Assert::notNull($date);

        $timeslotUuid = Uuid::fromString($request->string('timeSlotUuid')->toString());
        $category = $request->string('category')->toString();
        $description = $request->string('description')->toString();
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        try {
            $result = $this->scheduledSupportCallService->create(
                date: $date->toImmutable(),
                timeslotUuid: $timeslotUuid,
                category: $category,
                description: $description,
                customer: $customer
            );
        } catch (PuzzelResponseMissingRedirectException $exception) {
            $this->logger->error(
                'Could not create callback request in Puzzel',
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'callback_description' => $description,
                        'callback_scheduledTime' => $date,
                    ],
                ]
            );

            return $this->failedCallbackCreatedResponse();
        }

        if ($result->status !== Result::SUCCESS) {
            $this->logger->error(
                sprintf('Error received from puzzel during callback request: [%s]', $result->message),
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::META => [
                        'callback_description' => $description,
                        'callback_scheduledTime' => $date,
                        'status' => $result->status,
                        'message' => $result->message,
                    ],
                ]
            );

            return $this->failedCallbackCreatedResponse();
        }

        return new JsonResponse([
            'data' => [
                'status' => 'success',
                'message' => $this->translator->translate('puzzel.callback-created'),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * @throws AuthenticationException
     */
    public function timeslots(): ScheduledSupportCallTimeSlotsResource
    {
        $customer = $this->authenticationManager
            ->getAuthenticatedCustomer()
            ->customer;

        return ScheduledSupportCallTimeSlotsResource::make(
            $this->scheduledSupportCallService->getScheduleForCustomer($customer),
        );
    }

    private function failedCallbackCreatedResponse(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'status'  => 'error',
                'message' => $this->translator->translate('puzzel.callback-not-created'),
            ],
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
