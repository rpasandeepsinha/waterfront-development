<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Services;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365ProviderException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365RequestException;
use Waterfront\Domain\Provision\Microsoft365\Factories\Microsoft365ServiceFactory;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365AuthorizationUrlRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365CreateDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365DeleteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetServiceDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetVerificationDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365PromoteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365SetDomainAsDefaultDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365VerifyDomainRequest;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\AbstractProvisionService;

class Microsoft365ProvisionService extends AbstractProvisionService
{
    public function __construct(
        private readonly Microsoft365ServiceFactory $serviceFactory,
        private readonly MicrosoftOnlineService $microsoftOnlineService,
        private readonly MicrosoftGraphService $graphService,
    ) {
    }

    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        try {
            $validator = $this->serviceFactory->getValidator(
                $this->getProviderForRequest($provisionData),
                $provisionData,
            );

            if ($validator->fails()) {
                return $this->createFailedValidationResult($provisionData, $validator);
            }
        } catch (UnknownMicrosoft365ProviderException|UnknownMicrosoft365RequestException $providerException) {
            return new ProvisionResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return null;
    }

    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        return match ($provisionData::class) {
            Microsoft365AuthorizationUrlRequest::class => $this->microsoftOnlineService->getAuthorizationUrl(
                $provisionData,
            ),
            Microsoft365TenantIdRequest::class => $this->microsoftOnlineService->getTenantId($provisionData),
            Microsoft365GetDomainRequest::class => $this->graphService->getDomain($provisionData),
            Microsoft365VerifyDomainRequest::class => $this->graphService->verifyDomain($provisionData),
            Microsoft365CreateDomainRequest::class => $this->graphService->createDomain($provisionData),
            Microsoft365PromoteDomainRequest::class => $this->graphService->promoteDomain($provisionData),
            Microsoft365DeleteDomainRequest::class => $this->graphService->deleteDomain($provisionData),
            Microsoft365SetDomainAsDefaultDomainRequest::class => $this->graphService->setDomainToDefaultDomain(
                $provisionData,
            ),
            Microsoft365GetServiceDnsRecordsRequest::class => $this->graphService->getServiceDnsRecords($provisionData),
            Microsoft365GetVerificationDnsRecordsRequest::class => $this->graphService->getVerificationDnsRecords(
                $provisionData,
            ),
            default => new ProvisionResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new UnknownMicrosoft365RequestException($provisionData),
            ),
        };
    }

    public function getDefaultProvider(): ProvisionProvider
    {
        return ProvisionProvider::MICROSOFT_ONLINE;
    }
}
