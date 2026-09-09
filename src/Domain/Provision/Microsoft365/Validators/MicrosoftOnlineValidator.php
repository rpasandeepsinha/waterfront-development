<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Validators;

use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365RequestException;
use Waterfront\Domain\Provision\Microsoft365\Interfaces\Microsoft365RequestValidatorInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365AuthorizationUrlRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;

class MicrosoftOnlineValidator implements Microsoft365RequestValidatorInterface
{
    public function __construct(private readonly Factory $validatorFactory)
    {
    }

    /**
     * @throws UnknownMicrosoft365RequestException
     */
    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): ValidatorContract
    {
        return match ($provisionRequest::class) {
            Microsoft365TenantIdRequest::class => $this->getTenantIdRequestValidator($provisionRequest),
            Microsoft365AuthorizationUrlRequest::class => $this->getAuthorizationUrlRequestValidator($provisionRequest),
            default => throw new UnknownMicrosoft365RequestException($provisionRequest)
        };
    }

    public function getTenantIdRequestValidator(Microsoft365TenantIdRequest $tenantIdRequest): ValidatorContract
    {
        $requestData = (array) $tenantIdRequest;
        $rules = [
            'tenantName' => 'required|string',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getAuthorizationUrlRequestValidator(Microsoft365AuthorizationUrlRequest $authorizationUrlRequest): ValidatorContract
    {
        $requestData = (array) $authorizationUrlRequest;

        $rules = [
            'tenantName' => 'required|string',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }
}
