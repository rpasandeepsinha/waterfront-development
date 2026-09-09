<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Translations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<mixed> $resource
 */
class TranslationsResource extends JsonResource
{
    /**
     * @return array<mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'translations' => $this->resource['translations'],
        ];
    }
}
