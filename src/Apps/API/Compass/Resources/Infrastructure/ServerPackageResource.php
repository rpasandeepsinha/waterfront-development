<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Hosting\DTO\ServerPackageDTO;

/** @property ServerPackageDTO $resource */
class ServerPackageResource extends JsonResource
{
    /** @return array<string, array<mixed>> */
    public function toArray(Request $request): array
    {
        return [
            'details' => $this->resource->details,
            'errors' => $this->resource->errors,
        ];
    }
}
