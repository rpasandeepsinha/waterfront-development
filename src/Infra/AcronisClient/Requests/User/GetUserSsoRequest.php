<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\User;

use Ramsey\Uuid\UuidInterface;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class GetUserSsoRequest extends Request implements HasBody
{
    use HasJsonBody;

    private const string AUDIT_DATA_CUSTOMER_LOGIN = 'external system support, customer logged in';

    private const string AUDIT_DATA_EMPLOYEE_LOGIN = 'external system support, employee "%s" logged in';

    protected Method $method = Method::POST;

    public function __construct(
        private readonly UuidInterface $userId,
        private readonly ?string $employeeUuid = null,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/idp/ott';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'user_id' => $this->userId->toString(),
            'purpose' => 'user_login',
            'audit_data' => $this->employeeUuid === null
                ? self::AUDIT_DATA_CUSTOMER_LOGIN
                : sprintf(self::AUDIT_DATA_EMPLOYEE_LOGIN, $this->employeeUuid),
        ];
    }
}
