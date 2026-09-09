<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\OfferingItems;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class PutOfferingItemsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $tenantId,
        private readonly OfferingItems $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/offering_items', $this->tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return AcronisSerializer::get()->normalizeToArray(
            payload: $this->payload,
            context: ['groups' => 'put'],
        );
    }
}
