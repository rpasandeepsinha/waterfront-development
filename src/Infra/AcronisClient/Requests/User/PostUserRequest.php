<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\User;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\User;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class PostUserRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly User $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/users';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return AcronisSerializer::get()->normalizeToArray(
            payload: $this->payload,
            context: ['groups' => 'create'],
        );
    }
}
