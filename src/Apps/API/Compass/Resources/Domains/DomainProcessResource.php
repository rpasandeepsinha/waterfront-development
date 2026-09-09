<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RealtimeRegister\Domain\Process;

/**
 * @property Process $resource
 */
class DomainProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'user' => $this->resource->user,
            'customer' => $this->resource->customer,
            'status' => $this->resource->status,
            'status_detail' => $this->resource->statusDetail,
            'resume_types' => $this->resource->resumeTypes,
            'created_at' => CarbonImmutable::instance($this->resource->createdDate)->toISOString(),
            'updated_at' => CarbonImmutable::make($this->resource->updatedDate)?->toISOString(),
            'started_at' => CarbonImmutable::make($this->resource->startedDate)?->toISOString(),
            'type' => $this->resource->type,
            'identifier' => $this->resource->identifier,
            'action' => $this->resource->action,
            'reservation' => $this->resource->reservation,
            'transaction' => $this->resource->transaction,
            'refund' => $this->resource->refund,
            'command' => $this->resource->command,
            'billables' => $this->resource->billables?->toArray(),
        ];
    }
}
