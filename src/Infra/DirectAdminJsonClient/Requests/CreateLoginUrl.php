<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminJsonClient\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskKeysInterface;

class CreateLoginUrl extends Request implements HasBody, MaskKeysInterface
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $password)
    {
    }

    public function resolveEndpoint(): string
    {
        return '/api/login-keys/urls';
    }

    public function getMaskKeys(): array
    {
        return ['currentPassword', 'url'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'allowNetworks' => [],
            'currentPassword' => $this->password,
        ];
    }
}
