<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Harbor\DTO;

use Illuminate\Http\JsonResponse;
use JsonSerializable;
use Waterfront\Apps\API\Harbor\DTO\ResponseData\AbstractResponseData;

/**
 * Data structure for response bodies on base level.
 */
class HarborApiResponse implements JsonSerializable
{
    public function __construct(
        private readonly AbstractResponseData $data,
    ) {
    }

    /**
     * Constructs a JsonResponse through a standardized way for Harbor.
     */
    public static function withData(AbstractResponseData $data): JsonResponse
    {
        return new JsonResponse(new self($data));
    }

    public function getData(): AbstractResponseData
    {
        return $this->data;
    }

    /**
     * @return array<string, AbstractResponseData>
     */
    public function jsonSerialize(): array
    {
        return ['data' => $this->getData()];
    }
}
