<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Services;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Support\LogContextBuilder;

class ProvisionTraceabilityService
{
    /**
     * These fields should be removed from the normalized provision request data because
     * they already have a dedicated field in the database and should not be stored as
     * part of the specific provision request data JSON that is saved in the database.
     *
     * @var string[]
     */
    private const array DATABASE_COLUMNS = [
        'tagUuid',
        'tag',
        'context',
        'name',
        'type',
        'requiresValidation',
        'provider',
        'requestId',
        'retryOf',
        'retryRequester',
        'retry',
    ];

    public function __construct(
        private readonly ProvisionSerializeFactory $serializerFactory,
        private readonly LoggerInterface $logger,
        private readonly ProvisioningRequestRepository $provisioningRequestRepository,
    ) {
    }

    public function storeRequest(ProvisionRequestInterface $provisionData, ProvisionProvider $provisionProvider): int
    {
        $serializer = $this->serializerFactory->get();
        $serialized = $serializer->serialize(
            data: $provisionData,
            format: 'json',
            context: [
                // We ignore the requestId as it is not part of the request data and available on this object itself.
                AbstractNormalizer::IGNORED_ATTRIBUTES => self::DATABASE_COLUMNS,
            ]
        );

        $request = new ProvisioningRequest();
        $request->uuid = Uuid::uuid4();
        $request->tag = $provisionData->tag;
        $request->request_data = $serialized;
        $request->request_name = $provisionData->name;
        $request->request_type = $provisionData->type;

        if ($provisionData->isRetry()) {
            $retryOfRequest = $this->provisioningRequestRepository->findByUuid($provisionData->retryOf);

            if ($retryOfRequest !== null) {
                $request->retry_of_request_id = $retryOfRequest->id;
                $request->requested_by_uuid = $provisionData->retryRequester;
            }
        }

        if ($provisionData instanceof ProvisionContextRequestInterface) {
            $request->context_uuid = $provisionData->context;
        }

        $request->provision_provider = $provisionData->provider ?? $provisionProvider;
        $request->save();

        return $request->id;
    }

    public function storeResult(ProvisionResultInterface $data, int $requestId): void
    {
        try {
            $serializer = $this->serializerFactory->get();
            $serialized = $serializer->serialize($data, 'json');
        } catch (NotNormalizableValueException $exception) {
            $this->logger->error(
                'Error occurred when normalizing provision result, unable to store request',
                LogContextBuilder::for($data->provisionData)
                    ->withException($exception)
                    ->build()
            );
            return;
        }

        $result = new ProvisioningResult();
        $result->request_id = $requestId;
        $result->uuid = Uuid::uuid4();
        $result->response = $serialized;
        $result->status = $data->provisionStatus;
        $result->save();
    }
}
