<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Services;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Exceptions\RetryOriginNotFoundException;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\ProvisionGateway;

class ProvisionRetryService
{
    public function __construct(
        private readonly ProvisionSerializeFactory $serializerFactory,
        private readonly ProvisionGateway $provisionGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $retryData
     *
     * @throws RetryOriginNotFoundException
     * @throws SerializerExceptionInterface
     */
    public function retry(
        UuidInterface $retryOf,
        UuidInterface $retryRequester,
        array $retryData,
    ): ProvisionResultInterface {
        $originResult = $this->provisionGateway
            ->fetch(new ProvisioningResultQueryFilters(requestUuid: $retryOf), 1)
            ->first();

        if ($originResult === null) {
            throw new RetryOriginNotFoundException($retryOf);
        }

        $provisionRequest = $this->serializerFactory->get()->denormalize(
            data: [
                ...$retryData,
                'name' => $originResult->requestName->value,
                'context' => $originResult->context?->toString(),
                'tagUuid' => $originResult->tag->toString(),
            ],
            type: ProvisionRequestInterface::class,
        );

        $provisionRequest->tag = $originResult->tag;
        $provisionRequest->provider = $originResult->provider;
        $provisionRequest->retryOf = $retryOf;
        $provisionRequest->retryRequester = $retryRequester;

        return $this->provisionGateway->request($provisionRequest);
    }
}
