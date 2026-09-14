<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Services;

use Psr\Log\LoggerInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Exceptions\BaseKitClientException;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitAddSslException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitSiteRefNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserRefNotFoundForContextException;
use Waterfront\Domain\Provision\Sitebuilder\Interfaces\SitebuilderProvisionServiceInterface;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
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
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitUserResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderSsoResult;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Support\Enums\LoggingContextKeys;

class BasekitProvisionService implements SitebuilderProvisionServiceInterface
{
    public function __construct(
        private readonly CreateBasekitService $createBasekitService,
        private readonly BaseKit $basekitClient,
        private readonly ConnectorConfig $basekitConfig,
        private readonly SitebuilderDeploymentRepository $sitebuilderDeploymentRepository,
        private readonly LoggerInterface $logger,
        private readonly UpdateBasekitService $updateBasekitService,
        private readonly DeleteBasekitService $deleteBasekitService,
    ) {
    }

    public function create(CreateSitebuilderRequest $createSitebuilderRequest): SitebuilderResult
    {
        return $this->createBasekitService->create($createSitebuilderRequest);
    }

    public function createFromMigration(CreateBasekitDeploymentsFromMigrationRequest $provisionData): SitebuilderResult
    {
        return $this->createBasekitService->createFromMigration($provisionData);
    }

    public function rollbackFromMigration(RollbackBasekitDeploymentsFromMigrationRequest $provisionData): SitebuilderResult
    {
        return $this->deleteBasekitService->rollbackFromMigration($provisionData);
    }

    public function addSsl(AddSslSitebuilderRequest $provisionData): SitebuilderResult
    {
        $sitebuilderDeployment = $this->sitebuilderDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $sitebuilderDeployment instanceof SitebuilderDeployment) {
            return new SitebuilderResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No sitebuilder deployment found for the given tag [%s].', $provisionData->tagUuid),
                ),
            );
        }

        $this->logger->debug(
            'Adding SSL to Basekit sitebuilder deployment',
            LogContextBuilder::for($provisionData)->with(
                LoggingContextKeys::DOMAIN_NAME,
                $sitebuilderDeployment->domain,
            )->build(),
        );

        try {
            $this->basekitClient->sslApi->addSsl(
                domain: $sitebuilderDeployment->domain,
                privateKey: $provisionData->privateKey,
                certificate: $provisionData->mainCertificate,
            );
        } catch (BaseKitClientException $exception) {
            $this->logger->warning(
                'Adding SSL to Basekit sitebuilder deployment failed',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $sitebuilderDeployment->domain)
                    ->build(),
            );

            return new SitebuilderResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new BasekitAddSslException(previous: $exception),
            );
        }

        return new SitebuilderResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function getSso(GetSitebuilderSsoRequest $provisionData): SitebuilderSsoResult
    {
        $sitebuilderDeployment = $this->sitebuilderDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $sitebuilderDeployment instanceof SitebuilderDeployment) {
            return new SitebuilderSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No sitebuilder deployment found for the given tag [%s].', $provisionData->tagUuid),
                ),
            );
        }

        $siteRef = $sitebuilderDeployment->basekitDeployment?->site_ref;

        if ($siteRef === null) {
            return new SitebuilderSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new BasekitSiteRefNotFoundException($sitebuilderDeployment->uuid),
            );
        }

        $context = $sitebuilderDeployment->basekitContext;

        if (! $context instanceof BasekitContext) {
            return new SitebuilderSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new BasekitUserRefNotFoundForContextException($provisionData->context),
            );
        }

        try {
            $hash = $this->basekitClient->loginApi->autoLogin($context->user_ref);
        } catch (UnexpectedValueException $exception) {
            return new SitebuilderSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        $ssoUrl = sprintf(
            '%s/login?hash=%s&siteRef=%s',
            $this->basekitConfig->ssoUrl,
            rawurlencode($hash),
            $siteRef,
        );

        return new SitebuilderSsoResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            ssoUrl: $ssoUrl,
        );
    }

    public function terminateSitebuilder(TerminateSitebuilderRequest $provisionData): SitebuilderResult
    {
        return $this->deleteBasekitService->terminateSitebuilder($provisionData);
    }

    public function terminateByContext(TerminateSitebuilderContextRequest $provisionData): SitebuilderResult
    {
        return $this->deleteBasekitService->terminateByContext($provisionData);
    }

    public function update(UpdateSitebuilderRequest $updateSitebuilderRequest): SitebuilderResult
    {
        return $this->updateBasekitService->update($updateSitebuilderRequest);
    }

    public function getBasekitSiteByRef(GetBasekitSiteByRefRequest $provisionData): BasekitSiteResult
    {
        try {
            $baseKitSite = $this->basekitClient->sitesApi->get($provisionData->siteRef);

            return new BasekitSiteResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
                siteRef: $baseKitSite->ref,
                domain: $baseKitSite->primaryDomain->domainName,
            );
        } catch (BaseKitRequestException $exception) {
            $this->logger->error(
                'Unable to get basekit site by ref',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta(['site_ref' => $provisionData->siteRef])
                    ->build(),
            );

            return new BasekitSiteResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }

    public function getBasekitUserByRef(GetBasekitUserByRefRequest $provisionData): BasekitUserResult
    {
        try {
            $baseKitUser = $this->basekitClient->userApi->get($provisionData->userRef);

            return new BasekitUserResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
                userId: $baseKitUser->ref,
                email: $baseKitUser->email,
            );
        } catch (BaseKitRequestException $exception) {
            $this->logger->error(
                'Unable to get basekit user by ref',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta(['user_ref' => $provisionData->userRef])
                    ->build(),
            );

            return new BasekitUserResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }
}
