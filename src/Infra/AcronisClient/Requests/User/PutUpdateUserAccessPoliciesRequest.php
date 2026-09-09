<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\User;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\UserAccessPolicies;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class PutUpdateUserAccessPoliciesRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $userId,
        private readonly UserAccessPolicies $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/users/%s/access_policies', $this->userId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return AcronisSerializer::get()->normalizeToArray($this->payload);
    }
}
