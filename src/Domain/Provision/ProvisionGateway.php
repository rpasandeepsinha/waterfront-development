<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision;

use Error;
use Exception;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\StoreProvisionRequestException;
use Waterfront\Domain\Provision\Exceptions\StoreProvisionResultException;
use Waterfront\Domain\Provision\Factories\ProvisionServiceFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Domain\Provision\Validation\RetryRequestValidator;

class ProvisionGateway
{
    public function __construct(
        private readonly ProvisionServiceFactory $provisionServiceFactory,
        private readonly ProvisionTraceabilityService $provisionTraceabilityService,
        private readonly ProvisioningResultRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly RetryRequestValidator $retryRequestValidator,
    ) {
    }

    public function request(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        try {
            // Validate retries before we validate on service level.
            $retryValidationResult = $this->retryRequestValidator->validate($provisionData);

            if ($retryValidationResult instanceof ProvisionResultInterface) {
                return $retryValidationResult;
            }

            $service = $this->provisionServiceFactory->create($provisionData->type);
            $provider = $provisionData->provider ?? $service->getDefaultProvider();
            $provisionData->provider = $provider;
            $requestId = $this->storeRequest($provisionData, $provider);

            $provisionData->requestId = $requestId;

            if ($provisionData->requiresValidation) {
                $validationResult = $service->validate($provisionData);

                if ($validationResult instanceof ProvisionResultInterface) {
                    $this->storeResult($validationResult, $requestId);

                    return $validationResult;
                }
            }

            $result = $service->send($provisionData);
            $this->storeResult($result, $requestId);

            return $result;
        } catch (StoreProvisionResultException|StoreProvisionRequestException $exception) {
            return $this->failedResultFromException($exception, $provisionData);
        } catch (Exception $exception) {
            // if we didn't fail to store the result but failed on something else
            // such as an external client down the process, we still want to
            // try to store the failed provision result in our database.
            $failedResult = $this->failedResultFromException($exception, $provisionData);

            try {
                $requestId = $provisionData->requestId;
            } catch (Error) {
                // We catch Error here for the possibility of a not initialized error on the requestId property
                // which could happen if the error appeared before we could set it. Such as a db connection
                // error when trying to store the request. If this is the case we can't store the result
                return $failedResult;
            }

            try {
                $this->storeResult($failedResult, $requestId);
            } catch (StoreProvisionResultException $storeException) {
                return $this->failedResultFromException($storeException, $provisionData);
            }

            return $failedResult;
        }
    }

    /**
     * @return Collection<int, ProvisioningFilteredResult>
     */
    public function fetch(ProvisioningResultQueryFilters $filters, ?int $limit): Collection
    {
        return $this->repository->fetchProvisioningResults($filters, $limit);
    }

    private function failedResultFromException(Exception $exception, ProvisionRequestInterface $provisionRequest): ProvisionResultInterface
    {
        $this->logger->error(
            sprintf('Provisioning failed internally: %s', $exception->getMessage()),
            LogContextBuilder::for($provisionRequest)
                ->withException($exception)
                ->build()
        );

        return new ProvisionResult(
            provisionData: $provisionRequest,
            provisionStatus: ProvisionStatus::FAILED,
            exception: $exception,
        );
    }

    /**
     * @throws StoreProvisionResultException
     */
    private function storeResult(ProvisionResultInterface $validationResult, int $requestId): void
    {
        try {
            $this->provisionTraceabilityService->storeResult($validationResult, $requestId);
        } catch (Exception $exception) {
            throw new StoreProvisionResultException(
                result: $validationResult,
                originRequestId: $requestId,
                previous: $exception
            );
        }
    }

    /**
     * @throws StoreProvisionRequestException
     */
    private function storeRequest(ProvisionRequestInterface $provisionData, ProvisionProvider $provider): int
    {
        try {
            return $this->provisionTraceabilityService->storeRequest($provisionData, $provider);
        } catch (Exception $exception) {
            throw new StoreProvisionRequestException(
                request: $provisionData,
                previous: $exception
            );
        }
    }
}
