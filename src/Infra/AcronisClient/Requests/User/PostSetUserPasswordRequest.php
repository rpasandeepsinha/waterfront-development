<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\User;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

class PostSetUserPasswordRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $userId,
        #[SensitiveParameter]
        private readonly string $password,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/users/%s/password', $this->userId);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'password' => $this->password,
        ];
    }
}
