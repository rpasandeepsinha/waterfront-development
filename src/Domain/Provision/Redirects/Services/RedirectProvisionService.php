<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Services;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Redirects\Exceptions\UnknownRedirectProviderException;
use Waterfront\Domain\Provision\Redirects\Exceptions\UnknownRedirectRequestException;
use Waterfront\Domain\Provision\Redirects\Factories\RedirectServiceFactory;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Services\AbstractProvisionService;

class RedirectProvisionService extends AbstractProvisionService implements ProvisionServiceInterface
{
    public function __construct(
        private readonly RedirectServiceFactory $redirectServiceFactory,
    ) {
    }

    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        try {
            $validator = $this->redirectServiceFactory->getValidator(
                provider: $this->getProviderForRequest($provisionData),
                provisionRequest: $provisionData,
            );

            if ($validator->fails()) {
                return $this->createFailedValidationResult($provisionData, $validator);
            }
        } catch (UnknownRedirectProviderException|UnknownRedirectRequestException $providerException) {
            return new RedirectResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return null;
    }

    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        try {
            $redirectService = $this->redirectServiceFactory->getProviderService(
                provider: $this->getProviderForRequest($provisionData),
            );
        } catch (UnknownRedirectProviderException $providerException) {
            return new RedirectResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return match ($provisionData::class) {
            CreateRedirectRequest::class => $redirectService->createRedirect($provisionData),
            GetRedirectRequest::class => $redirectService->getRedirect($provisionData),
            ListRedirectsRequest::class => $redirectService->listRedirects($provisionData),
            UpdateRedirectRequest::class => $redirectService->updateRedirect($provisionData),
            DeleteRedirectRequest::class => $redirectService->deleteRedirect($provisionData),
            TerminateRedirectsRequest::class => $redirectService->terminateRedirects($provisionData),
            SuspendRedirectRequest::class => $redirectService->suspendRedirects($provisionData),
            UnsuspendRedirectRequest::class => $redirectService->unsuspendRedirects($provisionData),
            default => new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new UnknownRedirectRequestException($provisionData),
            ),
        };
    }

    public function getDefaultProvider(): ProvisionProvider
    {
        return ProvisionProvider::CADDY;
    }
}
