<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Waterfront\Apps\API\Compass\Requests\RetryProvisionRequest;
use Waterfront\Apps\API\Compass\Support\ProvisionRetryResponseMapper;
use Waterfront\Domain\Provision\Exceptions\RetryOriginNotFoundException;
use Waterfront\Domain\Provision\Services\ProvisionRetryService;
use Waterfront\Infra\Authentication\AuthenticationManager;

class ProvisionRetryController
{
    public function __construct(
        private readonly ProvisionRetryService $provisionRetryService,
        private readonly ProvisionRetryResponseMapper $responseMapper,
        private readonly AuthenticationManager $authenticationManager,
    ) {
    }

    public function retry(RetryProvisionRequest $request): JsonResponse
    {
        try {
            $result = $this->provisionRetryService->retry(
                retryOf: Uuid::fromString($request->string('retryOf')->toString()),
                retryRequester: $this->authenticationManager
                    ->getAuthenticatedEmployee()
                    ->getAuthIdentifier(),
                retryData: $request->array('retryData'),
            );
        } catch (RetryOriginNotFoundException|SerializerExceptionInterface $exception) {
            return $this->responseMapper->fromException($exception);
        }

        return $this->responseMapper->fromResult($result);
    }
}
