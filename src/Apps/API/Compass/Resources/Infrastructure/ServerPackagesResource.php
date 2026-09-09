<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Hosting\DTO\ServerPackagesDTO;

/** @property ServerPackagesDTO $resource */
class ServerPackagesResource extends JsonResource
{
    /** @return array<string, array<mixed>> */
    public function toArray(Request $request): array
    {
        return [
            'packages' => $this->resource->packages,
            'errors' => $this->resource->errors,
        ];
    }
}
