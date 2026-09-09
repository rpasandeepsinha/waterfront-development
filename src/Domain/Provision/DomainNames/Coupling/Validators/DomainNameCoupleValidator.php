<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Validators;

use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\UnknownDomainNameCoupleRequestException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Rules\DomainNameCoupleAllowedRule;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionTypeValidatorInterface;
use Waterfront\Domain\Provision\Rules\DeploymentExistsRule;

class DomainNameCoupleValidator implements ProvisionTypeValidatorInterface
{
    public function __construct(
        private readonly Factory $validatorFactory,
        private readonly DomainNameRule $domainNameRule,
        private readonly DeploymentExistsRule $deploymentExistsRule,
        private readonly DomainNameCoupleAllowedRule $domainNameCoupleAllowedRule,
    ) {
    }

    /**
     * @throws UnknownDomainNameCoupleRequestException
     */
    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): ValidatorContract
    {
        return match ($provisionRequest::class) {
            DomainNameDecoupleRequest::class => $this->getDecoupleRequestValidator($provisionRequest),
            DomainNameCoupleRequest::class   => $this->getCoupleRequestValidator($provisionRequest),
            default => throw new UnknownDomainNameCoupleRequestException($provisionRequest)
        };
    }

    private function getDecoupleRequestValidator(DomainNameDecoupleRequest $provisionRequest): ValidatorContract
    {
        return $this->validatorFactory->make(
            [
                'domain' => $provisionRequest->domain,
                'requestUuid' => $provisionRequest->requestUuid,
            ],
            [
                'domain' => ['required', $this->domainNameRule],
                'requestUuid' => ['required', $this->deploymentExistsRule, $this->domainNameCoupleAllowedRule],
            ]
        );
    }

    private function getCoupleRequestValidator(DomainNameCoupleRequest $provisionRequest): ValidatorContract
    {
        return $this->validatorFactory->make(
            [
                'domain' => $provisionRequest->domain,
                'requestUuid' => $provisionRequest->requestUuid,
            ],
            [
                'domain' => ['required', $this->domainNameRule],
                'requestUuid' => ['required', $this->deploymentExistsRule, $this->domainNameCoupleAllowedRule],
            ]
        );
    }
}
