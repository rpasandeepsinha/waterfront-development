<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Validators;

use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingRequestException;
use Waterfront\Domain\Provision\Hosting\Interfaces\HostingRequestValidatorInterface;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class PleskValidator implements HostingRequestValidatorInterface
{
    public function __construct(private readonly Factory $validatorFactory)
    {
    }

    public function getCreateRequestValidator(HostingCreateRequest $createRequest): ValidatorContract
    {
        $requestData = (array) $createRequest;
        $rules = [
            'servicePlan' => 'required',
            'email' => 'required|email',
            'contactName' => 'required',
            'ipv4' => 'required|ipv4',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): ValidatorContract
    {
        return match ($provisionRequest::class) {
            HostingCreateRequest::class => $this->getCreateRequestValidator($provisionRequest),
            default => throw new UnknownHostingRequestException($provisionRequest)
        };
    }
}
