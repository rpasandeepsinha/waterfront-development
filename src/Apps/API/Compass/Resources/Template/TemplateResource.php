<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Template;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Email\Models\Template;

/**
 * @property Template $resource
 */
class TemplateResource extends JsonResource
{
    /**
     * @return array<mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'subject' => $this->resource->subject,
            'header' => $this->resource->header,
            'body' => $this->resource->body,
            'slug' => $this->resource->slug,
            'footer' => $this->resource->footer,
            'hubspot_template_id' => $this->resource->hubspot_template_id,
        ];
    }
}
