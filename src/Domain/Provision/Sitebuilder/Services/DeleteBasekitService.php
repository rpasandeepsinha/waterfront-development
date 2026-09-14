<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Services;

use Exception;
use Psr\Log\LoggerInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitSiteRefNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserDeleteException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserRefNotFoundForContextException;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Support\Enums\LoggingContextKeys;

class DeleteBasekitService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BasekitContextRepository $baseKitContextRepository,
        private readonly SitebuilderDeploymentRepository $sitebuilderDeploymentRepository,
        private readonly BaseKit $basekitClient,
    ) {
    }

    public function terminateSitebuilder(TerminateSitebuilderRequest $provisionData): SitebuilderResult
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

        $siteRef = $sitebuilderDeployment->basekitDeployment?->site_ref;

        $this->logger->debug(
            'Terminating single basekit sitebuilder site',
            LogContextBuilder::for($provisionData)
                ->with(LoggingContextKeys::DOMAIN_NAME, $sitebuilderDeployment->domain)
                ->withMeta(['site_ref' => $sitebuilderDeployment->basekitDeployment?->site_ref])
                ->build(),
        );

        if ($siteRef === null) {
            return new SitebuilderResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new BasekitSiteRefNotFoundException($sitebuilderDeployment->uuid),
            );
        }

        try {
            $this->basekitClient->sitesApi->hardDelete($siteRef);
        } catch (BaseKitRequestException $exception) {
            $this->logger->warning(
                'Terminating single basekit sitebuilder site failed at basekit',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $sitebuilderDeployment->domain)
                    ->build(),
            );

            return new SitebuilderResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        try {
            $this->sitebuilderDeploymentRepository->deleteSitebuilderAndChildren($sitebuilderDeployment);
        } catch (Exception $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->warning(
                'Basekit sitebuilder deployment termination was already deleted.',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $sitebuilderDeployment->domain)
                    ->build(),
            );
        }

        return new SitebuilderResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function terminateByContext(TerminateSitebuilderContextRequest $provisionData): SitebuilderResult
    {
        $basekitContext = $this->baseKitContextRepository->findByContext($provisionData->context);

        if ($basekitContext === null) {
            return new SitebuilderResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new BasekitUserRefNotFoundForContextException($provisionData->context),
            );
        }

        try {
            $this->basekitClient->userApi->delete($basekitContext->user_ref);
        } catch (BaseKitRequestException $exception) {
            $this->logger->warning(
                'Failed to delete basekit user.',
                LogContextBuilder::for($provisionData)->withMeta(['user_ref' => $basekitContext->user_ref])->build(),
            );

            return new SitebuilderResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new BasekitUserDeleteException(previous: $exception),
            );
        }

        $sitebuilderDeployments =
            $this->sitebuilderDeploymentRepository->getSitebuilderDeploymentsByContext($basekitContext);

        foreach ($sitebuilderDeployments as $sitebuilderDeployment) {
            try {
                $this->logger->debug(
                    'Removing sitebuilder deployment and basekit deployment with given context.',
                    LogContextBuilder::for($provisionData)
                        ->with(LoggingContextKeys::DOMAIN_NAME, $sitebuilderDeployment->domain)
                        ->with(LoggingContextKeys::PROVISIONING_ID, $sitebuilderDeployment->id)
                        ->withMeta(['user_ref' => $basekitContext->user_ref])
                        ->build(),
                );

                $this->sitebuilderDeploymentRepository->deleteSitebuilderAndChildren($sitebuilderDeployment);
            } catch (Exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
                $this->logger->warning(
                    'Failed to delete sitebuilder deployment or basekit deployment, it was already missing.',
                    LogContextBuilder::for($provisionData)
                        ->with(LoggingContextKeys::DOMAIN_NAME, $sitebuilderDeployment->domain)
                        ->with(LoggingContextKeys::PROVISIONING_ID, $sitebuilderDeployment->id)
                        ->withMeta(['user_ref' => $basekitContext->user_ref])
                        ->build(),
                );
            }
        }

        $this->baseKitContextRepository->delete($basekitContext->context_uuid);

        return new SitebuilderResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function rollbackFromMigration(RollbackBasekitDeploymentsFromMigrationRequest $request): SitebuilderResult
    {
        $this->logger->debug(
            'Rolling back Basekit sitebuilder deployment from migration',
            LogContextBuilder::for($request)->build(),
        );

        $basekitContext = $this->baseKitContextRepository->findByContext($request->context);
        if ($basekitContext === null) {
            $this->logger->warning(
                'No Basekit context found for given context, nothing to remove',
                LogContextBuilder::for($request)->build(),
            );

            return new SitebuilderResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
            );
        }

        $sitebuilderDeployment = $this->sitebuilderDeploymentRepository->findByTag($request->tagUuid);

        if ($sitebuilderDeployment === null) {
            $this->logger->warning(
                'No Basekit sitebuilder deployment found for given tag, nothing to remove',
                LogContextBuilder::for($request)
                    ->with(LoggingContextKeys::PROVISIONING_CONTEXT, $basekitContext->context_uuid)
                    ->withMeta(['tag' => $request->tagUuid])
                    ->build(),
            );

            return new SitebuilderResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
            );
        }

        try {
            $this->sitebuilderDeploymentRepository->deleteSitebuilderAndChildren($sitebuilderDeployment);
            $deployments = $this->sitebuilderDeploymentRepository->getSitebuilderDeploymentsByContext($basekitContext);

            if ($deployments->isEmpty()) {
                $this->baseKitContextRepository->delete($basekitContext->context_uuid);
            }
        } catch (Exception $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->warning(
                'Rollback failed: Basekit deployment not found for sitebuilder deployment',
                LogContextBuilder::for($request)->withException($exception)->build(),
            );

            return new SitebuilderResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        return new SitebuilderResult(
            provisionData: $request,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }
}
