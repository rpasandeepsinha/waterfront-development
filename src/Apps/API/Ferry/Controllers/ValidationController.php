<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\ValidationRequest;
use Waterfront\Domain\Ferry\Actions\Validation\ExecuteValidationAction;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;

class ValidationController
{
    public function __construct(
        private readonly ResponseDto $responseDto,
        private readonly ExecuteValidationAction $executeValidationAction,
    ) {
    }

    public function validate(ValidationRequest $request): JsonResponse
    {
        $reference = (string) $request->string('reference');
        /** @var array<string, mixed> $customer */
        $customer = $request->input('customer', []);
        /** @var array<string, mixed> $subscriptions */
        $subscriptions = $request->input('subscriptions', []);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions,
        );

        $this->executeValidationAction->execute($validationPayload);

        $this->responseDto->addSuccess($this->makeSuccessDto($reference));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeSuccessDto(string $reference): SuccessDto
    {
        return SuccessDto::create(
            'Created pipeline to validate the customer payload',
            [
                Parameter::create('reference', $reference),
            ],
        );
    }
}
