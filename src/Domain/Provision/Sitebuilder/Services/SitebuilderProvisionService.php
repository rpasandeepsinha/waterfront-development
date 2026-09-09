<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Services;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Services\AbstractProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UnknownSitebuilderProviderException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UnknownSitebuilderRequestException;
use Waterfront\Domain\Provision\Sitebuilder\Factories\SitebuilderServiceFactory;
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
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;

class SitebuilderProvisionService extends AbstractProvisionService implements ProvisionServiceInterface
{
    public function __construct(
        private readonly SitebuilderServiceFactory $sitebuilderServiceFactory,
    ) {
    }

    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        try {
            $validator = $this->sitebuilderServiceFactory->getValidator(
                provider: $this->getProviderForRequest($provisionData),
                provisionRequest: $provisionData
            );

            if ($validator->fails()) {
                return $this->createFailedValidationResult($provisionData, $validator);
            }
        } catch (UnknownSitebuilderProviderException | UnknownSitebuilderRequestException $providerException) {
            return new SitebuilderResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return null;
    }

    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        try {
            $sitebuilderService = $this->sitebuilderServiceFactory->getProviderService(
                provider: $this->getProviderForRequest($provisionData)
            );
        } catch (UnknownSitebuilderProviderException $providerException) {
            return new SitebuilderResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        /*
            First we check generic sitebuilder requests and return null by default if none of the generic requests match
        */
        $result = match ($provisionData::class) {
            TerminateSitebuilderContextRequest::class => $sitebuilderService->terminateByContext($provisionData),
            CreateSitebuilderRequest::class => $sitebuilderService->create($provisionData),
            GetSitebuilderSsoRequest::class => $sitebuilderService->getSso($provisionData),
            AddSslSitebuilderRequest::class => $sitebuilderService->addSsl($provisionData),
            TerminateSitebuilderRequest::class => $sitebuilderService->terminateSitebuilder($provisionData),
            UpdateSitebuilderRequest::class => $sitebuilderService->update($provisionData),
            default => null
        };

        if ($result !== null) {
            return $result;
        }

        /*
            From here we check for provider specific requests that are not available on the inteface
        */
        if ($sitebuilderService instanceof BasekitProvisionService) {
            $result = match ($provisionData::class) {
                CreateBasekitDeploymentsFromMigrationRequest::class => $sitebuilderService->createFromMigration($provisionData),
                RollbackBasekitDeploymentsFromMigrationRequest::class => $sitebuilderService->rollbackFromMigration($provisionData),
                GetBasekitSiteByRefRequest::class => $sitebuilderService->getBasekitSiteByRef($provisionData),
                GetBasekitUserByRefRequest::class => $sitebuilderService->getBasekitUserByRef($provisionData),
                default => null
            };

            if ($result instanceof ProvisionResultInterface) {
                return $result;
            }
        }

        return new SitebuilderResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::FAILED,
            exception: new UnknownSitebuilderRequestException($provisionData)
        );
    }

    public function getDefaultProvider(): ProvisionProvider
    {
        return ProvisionProvider::BASEKIT;
    }
}
