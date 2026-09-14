<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources\CloudStack;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\VPS\Models\SshKey;

/**
 * @mixin SshKey
 */
class SshKeyResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'key_name' => $this->key_name,
            'public_key' => $this->public_key,
            'fingerprint' => $this->fingerprint,
            'created_at' => $this->created_at,
            'is_deletable' => $this->virtualMachineDeployments()->count() === 0,
        ];
    }
}
