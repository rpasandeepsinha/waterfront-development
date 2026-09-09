<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Validators;

use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Domains\Rules\RedirectDestinationUrlRule;
use Waterfront\Domain\Domains\Rules\RedirectFromUrlRule;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\UnknownRedirectRequestException;
use Waterfront\Domain\Provision\Redirects\Interfaces\RedirectValidatorInterface;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;

class CaddyValidator implements RedirectValidatorInterface
{
    public function __construct(
        private readonly Factory $validatorFactory,
        private readonly RedirectFromUrlRule $redirectFromUrlRule,
        private readonly RedirectDestinationUrlRule $redirectDestinationUrlRule,
    ) {
    }

    /**
     * @throws UnknownRedirectRequestException
     */
    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): ValidatorContract
    {
        return match ($provisionRequest::class) {
            CreateRedirectRequest::class => $this->getCreateRedirectValidator($provisionRequest),
            GetRedirectRequest::class => $this->getRedirectValidator($provisionRequest),
            ListRedirectsRequest::class => $this->getListRedirectsValidator($provisionRequest),
            UpdateRedirectRequest::class => $this->getUpdateRedirectValidator($provisionRequest),
            DeleteRedirectRequest::class => $this->getDeleteRedirectValidator($provisionRequest),
            TerminateRedirectsRequest::class => $this->getTerminateRedirectsValidator($provisionRequest),
            SuspendRedirectRequest::class => $this->getContextOnlyValidator($provisionRequest),
            UnsuspendRedirectRequest::class => $this->getContextOnlyValidator($provisionRequest),
            default => throw new UnknownRedirectRequestException($provisionRequest)
        };
    }

    public function getCreateRedirectValidator(CreateRedirectRequest $createRedirectRequest): ValidatorContract
    {
        $requestData = [
            'domain' => $createRedirectRequest->domain,
            'destinationUrl' => $createRedirectRequest->destinationUrl,
            'redirectType' => $createRedirectRequest->redirectType,
            'context' => $createRedirectRequest->context->toString(),
        ];

        $rules = [
            'domain' => ['required', $this->redirectFromUrlRule, 'max:255', Rule::unique('redirect_deployments', 'source')->withoutTrashed()],
            'destinationUrl' => ['required', $this->redirectDestinationUrlRule, 'max:255'],
            'redirectType'   => ['required', Rule::enum(RedirectType::class)],
            'context'        => ['required', 'uuid'],
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getRedirectValidator(GetRedirectRequest $getRedirectRequest): ValidatorContract
    {
        $requestData = [
            'domainName' => $getRedirectRequest->domainName,
            'context' => $getRedirectRequest->context->toString(),
        ];

        $rules = [
            'domainName' => ['required', $this->redirectFromUrlRule, 'max:255', 'exists:redirect_deployments,source'],
            'context' => ['required', 'uuid', 'exists:redirect_deployments,context_uuid'],
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getListRedirectsValidator(ListRedirectsRequest $getRedirectsByContextRequest): ValidatorContract
    {
        $requestData = [
            'context' => $getRedirectsByContextRequest->context->toString(),
        ];

        $rules = [
            'context' => ['required', 'uuid'],
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getUpdateRedirectValidator(UpdateRedirectRequest $updateRedirectRequest): ValidatorContract
    {
        $requestData = [
            'oldSource' => $updateRedirectRequest->oldSource,
            'newSource' => $updateRedirectRequest->newSource,
            'destinationUrl' => $updateRedirectRequest->destinationUrl,
            'redirectType' => $updateRedirectRequest->redirectType,
            'context' => $updateRedirectRequest->context->toString(),
        ];

        $rules = [
            'oldSource' => ['required', $this->redirectFromUrlRule, 'max:255', 'exists:redirect_deployments,source'],
            'newSource' => ['required', $this->redirectFromUrlRule, 'max:255'],
            'destinationUrl' => ['required', $this->redirectDestinationUrlRule, 'max:255'],
            'redirectType' => ['required', Rule::enum(RedirectType::class)],
            'context' => 'required|uuid|exists:redirects_context_caddy,context_uuid',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getDeleteRedirectValidator(DeleteRedirectRequest $deleteRedirectRequest): ValidatorContract
    {
        $requestData = [
            'domainName' => $deleteRedirectRequest->domainName,
            'context' => $deleteRedirectRequest->context->toString(),
        ];

        $rules = [
            'domainName' => ['required', $this->redirectFromUrlRule, 'max:255', 'exists:redirect_deployments,source'],
            'context' => ['required', 'uuid', 'exists:redirect_deployments,context_uuid'],
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getTerminateRedirectsValidator(TerminateRedirectsRequest $terminateRedirectsRequest): ValidatorContract
    {
        $requestData = [
            'context' => $terminateRedirectsRequest->context->toString(),
        ];

        $rules = [
            'context' => ['required', 'uuid', 'exists:redirect_deployments,context_uuid'],
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getContextOnlyValidator(SuspendRedirectRequest|UnsuspendRedirectRequest $request): ValidatorContract
    {
        $data = [
            'context' => $request->context->toString(),
        ];

        $rules = [
            'context' => [
                'required',
                'uuid',
                Rule::exists('redirects_context_caddy', 'context_uuid')->withoutTrashed(),
            ],
        ];

        return $this->validatorFactory->make($data, $rules);
    }
}
