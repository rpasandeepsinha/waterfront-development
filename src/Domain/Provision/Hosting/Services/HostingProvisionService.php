<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Services;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingProviderException;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingRequestException;
use Waterfront\Domain\Provision\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingSsoRequest;
use Waterfront\Domain\Provision\Hosting\Results\HostingResult;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Services\AbstractProvisionService;

class HostingProvisionService extends AbstractProvisionService implements ProvisionServiceInterface
{
    public function __construct(private readonly HostingServiceFactory $hostingServiceFactory)
    {
    }

    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        try {
            $validator = $this->hostingServiceFactory->getValidator(
                $this->getProviderForRequest($provisionData),
                $provisionData
            );

            if ($validator->fails()) {
                return $this->createFailedValidationResult($provisionData, $validator);
            }
        } catch (UnknownHostingProviderException|UnknownHostingRequestException $providerException) {
            return new HostingResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return null;
    }

    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        try {
            $providerService = $this->hostingServiceFactory->getProviderService(
                $this->getProviderForRequest($provisionData)
            );
        } catch (UnknownHostingProviderException $providerException) {
            return new HostingResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return match ($provisionData::class) {
            HostingCreateRequest::class => $providerService->create($provisionData),
            HostingSsoRequest::class => $providerService->getSso($provisionData),
            default => new HostingResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new UnknownHostingRequestException($provisionData)
            )
        };
    }

    public function getDefaultProvider(): ProvisionProvider
    {
        return ProvisionProvider::PLESK;
    }
}
