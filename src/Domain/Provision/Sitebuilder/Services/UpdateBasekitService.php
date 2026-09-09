<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Services;

use Psr\Log\LoggerInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountPackage;
use SandwaveIo\BaseKit\Exceptions\BaseKitClientException;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitAddPackageForUserException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitDeletePackageForUserException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitGetPackagesForUserException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserRefNotFoundForContextException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UpdateBasekitException;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Support\LogContextBuilder;

class UpdateBasekitService
{
    public function __construct(
        private readonly BaseKit $baseKitClient,
        private readonly LoggerInterface $logger,
        private readonly BasekitContextRepository $baseKitContextRepository,
    ) {
    }

    public function update(UpdateSitebuilderRequest $updateSitebuilderRequest): SitebuilderResult
    {
        $basekitContext = $this->baseKitContextRepository->findByContext($updateSitebuilderRequest->context);
        if (! $basekitContext instanceof BasekitContext) {
            $this->logger->error(
                sprintf(
                    'Failed to retrieve context "%s"',
                    $updateSitebuilderRequest->context
                ),
                LogContextBuilder::for($updateSitebuilderRequest)
                    ->withException($exception = new BasekitUserRefNotFoundForContextException($updateSitebuilderRequest->context))
                    ->build()
            );

            return new SitebuilderResult(
                provisionData: $updateSitebuilderRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        try {
            $this->updateUserPackages($basekitContext, $updateSitebuilderRequest);
        } catch (BasekitException $exception) {
            return new SitebuilderResult(
                provisionData: $updateSitebuilderRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new UpdateBasekitException(
                    message: $exception->getMessage(),
                    code: $exception->getCode(),
                    previous: $exception
                ),
            );
        }

        return new SitebuilderResult(
            provisionData: $updateSitebuilderRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    /**
     * @param AccountPackage[] $accountPackages
     *
     * @return array<int, array<string, int>>
     */
    private function formatCurrentAccountPackagesForLogging(array $accountPackages): array
    {
        return array_map(fn ($accountPackage) => [
            'basekit_account_package_ref' => $accountPackage->ref,
            'basekit_package_ref' => $accountPackage->package->ref,
            'basekit_contract_period' => $accountPackage->billingPeriodMonths,
        ], $accountPackages);
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function formatNewAccountPackagesForLogging(UpdateSitebuilderRequest $updateSitebuilderRequest): array
    {
        return array_map(fn ($packageId) => [
            'basekit_package_ref' => $packageId,
            'basekit_contract_period' => $updateSitebuilderRequest->contractPeriod,
        ], $updateSitebuilderRequest->packages);
    }

    private function updateUserPackages(BasekitContext $basekitContext, UpdateSitebuilderRequest $updateSitebuilderRequest): void
    {
        $accountPackages = $this->getUserPackages($basekitContext, $updateSitebuilderRequest);
        $currentPackageIds = [];
        $basekitPackageStateMeta = [
            'current' => $this->formatCurrentAccountPackagesForLogging($accountPackages),
            'new' => $this->formatNewAccountPackagesForLogging($updateSitebuilderRequest),
        ];

        foreach ($accountPackages as $accountPackage) {
            if (
                $accountPackage->billingPeriodMonths !== $updateSitebuilderRequest->contractPeriod
                || ! in_array($accountPackage->package->ref, $updateSitebuilderRequest->packages, true)
            ) {
                $this->logger->debug(
                    sprintf(
                        'Start deleting account package %d with package reference %d for user %d',
                        $accountPackage->ref,
                        $accountPackage->package->ref,
                        $basekitContext->user_ref
                    ),
                    LogContextBuilder::for($updateSitebuilderRequest)
                        ->withMeta([
                            'basekit_account_package_ref' => $accountPackage->ref,
                            'basekit_package_ref' => $accountPackage->package->ref,
                            'basekit_user_ref' => $basekitContext->user_ref,
                            'basekit_packages' => $basekitPackageStateMeta,
                        ])
                        ->build()
                );
                $this->deleteUserPackage($basekitContext, $accountPackage, $updateSitebuilderRequest);
            } else {
                $currentPackageIds[] = $accountPackage->package->ref;
            }
        }

        $packagesToAdd = array_diff($updateSitebuilderRequest->packages, $currentPackageIds);
        foreach ($packagesToAdd as $packageRef) {
            $this->logger->debug(
                sprintf(
                    'Start adding package %d for user %d',
                    $packageRef,
                    $basekitContext->user_ref
                ),
                LogContextBuilder::for($updateSitebuilderRequest)
                    ->withMeta([
                        'basekit_package_ref' => $packageRef,
                        'basekit_user_ref' => $basekitContext->user_ref,
                        'basekit_packages' => $basekitPackageStateMeta,
                    ])
                    ->build()
            );

            $this->addUserPackage($basekitContext, $packageRef, $updateSitebuilderRequest);
        }
    }

    /**
     * @return AccountPackage[]
     */
    private function getUserPackages(BasekitContext $basekitContext, UpdateSitebuilderRequest $request): array
    {
        try {
            return $this->baseKitClient->packageApi->listUserPackages(
                $basekitContext->user_ref,
            );
        } catch (BaseKitClientException|UnexpectedValueException $exception) {
            $this->logger->error(
                sprintf(
                    'Failed to retrieve user packages for user %d',
                    $basekitContext->user_ref
                ),
                LogContextBuilder::for($request)
                    ->withException($exception)
                    ->withMeta([
                        'basekit_user_ref' => $basekitContext->user_ref,
                    ])
                    ->build()
            );

            throw new BasekitGetPackagesForUserException(
                userReference: $basekitContext->user_ref,
                previous: $exception,
            );
        }
    }

    private function deleteUserPackage(BasekitContext $basekitContext, AccountPackage $accountPackage, UpdateSitebuilderRequest $request): void
    {
        try {
            $this->baseKitClient->packageApi->deleteUserPackage(
                $basekitContext->user_ref,
                $accountPackage->ref,
            );
        } catch (BaseKitClientException|UnexpectedValueException $exception) {
            $this->logger->error(
                sprintf(
                    'Failed to delete account package %d for user %d',
                    $accountPackage->package->ref,
                    $basekitContext->user_ref
                ),
                LogContextBuilder::for($request)
                    ->withException($exception)
                    ->withMeta([
                        'basekit_account_package_ref' => $accountPackage->ref,
                        'basekit_package_ref' => $accountPackage->package->ref,
                        'basekit_user_ref' => $basekitContext->user_ref,
                    ])
                    ->build()
            );
            throw new BasekitDeletePackageForUserException($accountPackage->package->ref, $basekitContext->user_ref);
        }
    }

    private function addUserPackage(BasekitContext $basekitContext, int $packageRef, UpdateSitebuilderRequest $request): void
    {
        try {
            $this->baseKitClient->packageApi->addUserPackage(
                $basekitContext->user_ref,
                $packageRef,
                $request->contractPeriod
            );
        } catch (BaseKitClientException|UnexpectedValueException $exception) {
            $this->logger->error(
                sprintf(
                    'Failed to add package %d for user %d',
                    $packageRef,
                    $basekitContext->user_ref
                ),
                LogContextBuilder::for($request)
                    ->withException($exception)
                    ->withMeta([
                        'basekit_package_ref' => $packageRef,
                        'basekit_user_ref' => $basekitContext->user_ref,
                        'basekit_subscription_period' => $request->contractPeriod,
                    ])
                    ->build()
            );

            throw new BasekitAddPackageForUserException(
                packageReference: $packageRef,
                userReference: $basekitContext->user_ref,
                previous: $exception,
            );
        }
    }
}
