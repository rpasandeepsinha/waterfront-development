<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Validators;

use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Rules\ShouldHaveOneExistingCreateTag;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UnknownSitebuilderRequestException;
use Waterfront\Domain\Provision\Sitebuilder\Interfaces\SitebuilderValidatorInterface;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;

class BasekitValidator implements SitebuilderValidatorInterface
{
    public function __construct(
        private readonly Factory $validatorFactory,
        private readonly DomainNameRule $domainNameRule,
    ) {
    }

    /**
     * @throws UnknownSitebuilderRequestException
     */
    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): ValidatorContract
    {
        return match ($provisionRequest::class) {
            TerminateSitebuilderContextRequest::class => $this->getTerminateContextRequestValidator($provisionRequest),
            CreateSitebuilderRequest::class => $this->getCreateSitebuilderValidator($provisionRequest),
            CreateBasekitDeploymentsFromMigrationRequest::class => $this->getCreateBasekitFromMigrationValidator($provisionRequest),
            RollbackBasekitDeploymentsFromMigrationRequest::class => $this->getRollbackBasekitFromMigrationValidator($provisionRequest),
            GetSitebuilderSsoRequest::class => $this->getSsoRequestValidator($provisionRequest),
            AddSslSitebuilderRequest::class => $this->getAddSslSitebuilderValidator($provisionRequest),
            TerminateSitebuilderRequest::class => $this->getTerminateSitebuilderSiteRequestValidator($provisionRequest),
            UpdateSitebuilderRequest::class => $this->getUpdateRequestValidator($provisionRequest),
            GetBasekitSiteByRefRequest::class => $this->getGetBasekitSiteByRefRequestValidator($provisionRequest),
            GetBasekitUserByRefRequest::class => $this->getGetBasekitUserByRefRequestValidator($provisionRequest),
            default => throw new UnknownSitebuilderRequestException($provisionRequest)
        };
    }

    public function getCreateSitebuilderValidator(CreateSitebuilderRequest $createSitebuilderRequest): ValidatorContract
    {
        $requestData = [
            'domain' => $createSitebuilderRequest->domain,
            'packages' => $createSitebuilderRequest->packages,
            'firstname' => $createSitebuilderRequest->firstname,
            'lastname' => $createSitebuilderRequest->lastname,
            'email' => $createSitebuilderRequest->email,
            'contractPeriod' => $createSitebuilderRequest->contractPeriod,
            'context' => $createSitebuilderRequest->context->toString(),
        ];
        $rules = [
            'domain' => ['required', $this->domainNameRule, 'max:255'],
            'packages' => 'required|array',
            'packages.*' => 'integer',
            'firstname' => 'required|string|min:1|max:150',
            'lastname' => 'required|string|min:1|max:150',
            'email' => 'required|email|max:254',
            'contractPeriod' => 'required|integer|min:1',
            'context' => 'required|uuid',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getCreateBasekitFromMigrationValidator(CreateBasekitDeploymentsFromMigrationRequest $request): ValidatorContract
    {
        $requestData = [
            'provider' => $request->provider,
            'domain' => $request->domain,
            'userRef' => $request->userRef,
            'siteRef' => $request->siteRef,
            'context' => $request->context->toString(),
        ];

        $rules = [
            'provider' => [Rule::enum(ProvisionProvider::class)->only(ProvisionProvider::BASEKIT)],
            'domain' => ['required', $this->domainNameRule, 'max:255'],
            'userRef' => 'required|integer|min:1',
            'siteRef' => 'required|integer|min:1',
            'context' => 'required|uuid',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getRollbackBasekitFromMigrationValidator(RollbackBasekitDeploymentsFromMigrationRequest $request): ValidatorContract
    {
        return $this->getContextAndTagValidator(
            contextUuid: $request->context->toString(),
            tag: $request->tag->toString(),
        );
    }

    public function getTerminateContextRequestValidator(TerminateSitebuilderContextRequest $terminateRequest): ValidatorContract
    {
        $requestData = ['context' => $terminateRequest->context->toString()];

        $rules = [
            'context' => 'required|uuid|exists:sitebuilder_context_basekit,context_uuid',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getSsoRequestValidator(GetSitebuilderSsoRequest $request): ValidatorContract
    {
        return $this->getContextAndTagValidator(
            contextUuid: $request->context->toString(),
            tag: $request->tag->toString(),
        );
    }

    public function getTerminateSitebuilderSiteRequestValidator(TerminateSitebuilderRequest $request): ValidatorContract
    {
        return $this->getContextAndTagValidator(
            contextUuid: $request->context->toString(),
            tag: $request->tag->toString(),
        );
    }

    public function getAddSslSitebuilderValidator(AddSslSitebuilderRequest $request): ValidatorContract
    {
        $data = [
            'tag' => $request->tag->toString(),
            'context' => $request->context->toString(),
            'privateKey' => $request->privateKey,
            'mainCertificate' => $request->mainCertificate,
        ];

        $rules = [
            'context' => 'required|uuid|exists:sitebuilder_context_basekit,context_uuid',
            'tag' => [
                'bail',
                'required',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
            'privateKey' => 'required|string',
            'mainCertificate' => 'required|string',
        ];

        return $this->validatorFactory->make($data, $rules);
    }

    public function getUpdateRequestValidator(UpdateSitebuilderRequest $request): ValidatorContract
    {
        $requestData = [
            'tag' => $request->tag->toString(),
            'context' => $request->context->toString(),
            'packages' => $request->packages,
            'contractPeriod' => $request->contractPeriod,
        ];

        $rules = [
            'context' => 'required|uuid|exists:sitebuilder_context_basekit,context_uuid',
            'tag' => [
                'bail',
                'required',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
            'packages' => 'required|array',
            'packages.*' => 'integer',
            'contractPeriod' => 'required|integer|min:1',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getGetBasekitSiteByRefRequestValidator(GetBasekitSiteByRefRequest $request): ValidatorContract
    {
        $requestData = [
            'provider' => $request->provider,
            'siteRef' => $request->siteRef,
        ];

        $rules = [
            'provider' => [Rule::enum(ProvisionProvider::class)->only(ProvisionProvider::BASEKIT)],
            'siteRef' => 'required|integer|min:1',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getGetBasekitUserByRefRequestValidator(GetBasekitUserByRefRequest $request): ValidatorContract
    {
        $requestData = [
            'provider' => $request->provider,
            'userRef' => $request->userRef,
        ];

        $rules = [
            'provider' => [Rule::enum(ProvisionProvider::class)->only(ProvisionProvider::BASEKIT)],
            'userRef' => 'required|integer|min:1',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    private function getContextAndTagValidator(string $contextUuid, string $tag): ValidatorContract
    {
        $data = [
            'context' => $contextUuid,
            'tag' => $tag,
        ];

        $rules = [
            'context' => 'required|uuid|exists:sitebuilder_context_basekit,context_uuid',
            'tag' => [
                'bail',
                'required',
                'uuid',
                new ShouldHaveOneExistingCreateTag(ProvisionType::SITEBUILDER),
            ],
        ];

        return $this->validatorFactory->make($data, $rules);
    }
}
