<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;

/**
 * @property PuzzelCallbackTimeslot $resource
 */
class ScheduledSupportCallTimeslotResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array{uuid: string, display: string}
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid->toString(),
            'display' => sprintf(
                '%s - %s',
                $this->resource->start_timeslot->format('H:i'),
                $this->resource->end_timeslot->format('H:i'),
            ),
        ];
    }
}
