<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\EmailHistory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Resources\Template\TemplateResource;
use Waterfront\Domain\Email\Models\EmailHistory;

/**
 * @property EmailHistory $resource
 */
class EmailHistoryResource extends JsonResource
{
    /**
     * @return array<mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'receiver_email' => $this->resource->receiver_email,
            'receiver_uuid' => $this->resource->receiver_uuid,
            'receiver_type' => $this->resource->receiver_type,
            'template' => TemplateResource::make($this->resource->template),
            'sent_at' => $this->resource->sent_at,
            'payload' => $this->resource->payload,
            'hubspot_id' => $this->resource->hubspot_id,
            'hubspot_status' => $this->resource->hubspot_status,
            'last_result' => $this->resource->last_result,
        ];
    }
}
