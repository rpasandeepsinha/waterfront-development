<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Resources\RetentionToolkit\RetentionToolkitResource;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;

class RetentionToolkitResponseMapper
{
    /**
     * @param list<RetentionOfferItemCalculationDTO> $results
     */
    public function fromResults(array $results): ResourceCollection|JsonResponse
    {
        $errors = [];

        foreach ($results as $index => $result) {
            if ($result->status === RetentionOfferCalculationStatus::CALCULATED) {
                continue;
            }

            $errors['items.' . $index] = [
                $result->reason ?? 'The selected retention action could not be calculated.',
            ];
        }

        if ($errors !== []) {
            return new JsonResponse([
                'message' => 'One or more retention toolkit items could not be calculated.',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return RetentionToolkitResource::collection($results);
    }
}
