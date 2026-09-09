<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Hosting\DTO\FetchedServerUserDTO;

/** @property FetchedServerUserDTO $resource */
class FetchedServerUserResource extends JsonResource
{
    /** @return array<string, array<mixed>|string|null> */
    public function toArray(Request $request): array
    {
        return [
            'user_data' => $this->resource->userData,
            'sso_url' => $this->resource->ssoUrl,
            'mail_forwards' => $this->resource->mailForwards,
            'mail_users' => $this->resource->mailUsers,
            'errors' => $this->resource->errors,
        ];
    }
}
