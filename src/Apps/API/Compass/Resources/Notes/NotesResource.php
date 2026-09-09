<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Notes;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Notes\Models\Notes;

/** @property Notes $resource */
class NotesResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'subscription_id' => $this->resource->subscription->id ?? null,
            'note' => $this->resource->note,
            'created_at' => $this->resource->created_at,
            'noted_by' => $this->notedBy($this->resource),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function notedBy(Notes $note): array
    {
        $notedBy = [];

        if ($note->noted_by_uuid !== null) {
            $notedBy = ['noted_by_uuid' => $note->noted_by_uuid];
        }

        if ($note->noted_by_metadata !== null) {
            $notedBy = [...$notedBy, 'noted_by_metadata' => json_decode($note->noted_by_metadata, true)];
        }

        return $notedBy;
    }
}
