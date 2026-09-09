<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\Site;
use SandwaveIo\BaseKit\Exceptions\BaseKitClientException;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitAddPackageException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitCreateSiteException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserCreateException;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitSitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Infra\PasswordGenerator\AlphaNumericGenerator;
use Waterfront\Infra\PasswordGenerator\DefaultGenerator;
use Waterfront\Support\Enums\LoggingContextKeys;

class CreateBasekitService
{
    private const string LOCALE = 'nl';

    public function __construct(
        private readonly BaseKit $baseKitClient,
        private readonly ConnectorConfig $config,
        private readonly LoggerInterface $logger,
        private readonly BasekitContextRepository $baseKitContextRepository,
        private readonly SitebuilderDeploymentRepository $sitebuilderDeploymentRepository,
        private readonly BasekitSitebuilderDeploymentRepository $basekitSitebuilderDeploymentRepository,
        private readonly AlphaNumericGenerator $usernameGenerator,
        private readonly DefaultGenerator $passwordGenerator,
    ) {
    }

    public function create(CreateSitebuilderRequest $createSitebuilderRequest): SitebuilderResult
    {
        $this->logger->debug(
            'Provisioning new Basekit sitebuilder deployment',
            LogContextBuilder::for($createSitebuilderRequest)
                ->with(LoggingContextKeys::DOMAIN_NAME, $createSitebuilderRequest->domain)
                ->build()
        );

        try {
            $basekitContext = $this->baseKitContextRepository->findByContext($createSitebuilderRequest->context);

            if ($basekitContext === null) {
                $this->logger->debug(
                    'Creating new Basekit user / context',
                    LogContextBuilder::for($createSitebuilderRequest)
                        ->with(LoggingContextKeys::DOMAIN_NAME, $createSitebuilderRequest->domain)
                        ->build()
                );

                $userReference = $this->createUser($createSitebuilderRequest);

                $basekitContext = $this->baseKitContextRepository->create($createSitebuilderRequest->context, $userReference);
            }

            foreach ($createSitebuilderRequest->packages as $packageReference) {
                $this->addUserPackage(
                    request: $createSitebuilderRequest,
                    userReference: $basekitContext->user_ref,
                    packageRef: $packageReference,
                );
            }

            $externalSite = $this->createExternalSite(
                request: $createSitebuilderRequest,
                userReference: $basekitContext->user_ref,
            );
        } catch (BasekitException | UnexpectedValueException $exception) {
            $this->logger->warning(
                'Provisioning Basekit sitebuilder deployment failed at basekit',
                LogContextBuilder::for($createSitebuilderRequest)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $createSitebuilderRequest->domain)
                    ->build()
            );

            return new SitebuilderResult(
                provisionData: $createSitebuilderRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        try {
            DB::beginTransaction();
            $sitebuilderDeployment = $this->sitebuilderDeploymentRepository->create(
                requestId: $createSitebuilderRequest->requestId,
                domain: $createSitebuilderRequest->domain
            );
            $this->basekitSitebuilderDeploymentRepository->create($sitebuilderDeployment, $externalSite->ref);
            DB::commit();
        } catch (Exception $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            DB::rollBack();

            $this->logger->warning(
                'Provisioning Basekit sitebuilder deployment failed at database',
                LogContextBuilder::for($createSitebuilderRequest)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $createSitebuilderRequest->domain)
                    ->build()
            );

            return new SitebuilderResult(
                provisionData: $createSitebuilderRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        return new SitebuilderResult(
            provisionData: $createSitebuilderRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function createFromMigration(CreateBasekitDeploymentsFromMigrationRequest $createBasekitDeploymentsFromMigrationRequest): SitebuilderResult
    {
        $this->logger->debug(
            'Provisioning new Basekit sitebuilder deployment from migration',
            LogContextBuilder::for($createBasekitDeploymentsFromMigrationRequest)
                ->with(LoggingContextKeys::DOMAIN_NAME, $createBasekitDeploymentsFromMigrationRequest->domain)
                ->build()
        );

        $basekitContext = $this->baseKitContextRepository->findWithTrashedByContext(
            $createBasekitDeploymentsFromMigrationRequest->context
        );

        try {
            DB::beginTransaction();

            if ($basekitContext === null) {
                $this->logger->debug(
                    'Creating new Basekit user / context',
                    LogContextBuilder::for($createBasekitDeploymentsFromMigrationRequest)
                        ->with(LoggingContextKeys::DOMAIN_NAME, $createBasekitDeploymentsFromMigrationRequest->domain)
                        ->build()
                );

                $basekitContext = $this->baseKitContextRepository->create(
                    context: $createBasekitDeploymentsFromMigrationRequest->context,
                    userReference: $createBasekitDeploymentsFromMigrationRequest->userRef
                );
            }

            if ($basekitContext->trashed()) {
                $this->logger->debug(
                    'Restoring soft deleted Basekit context for migration',
                    LogContextBuilder::for($createBasekitDeploymentsFromMigrationRequest)->build()
                );

                $basekitContext->restore();
            }

            $sitebuilderDeployment = $this->sitebuilderDeploymentRepository->create(
                requestId: $createBasekitDeploymentsFromMigrationRequest->requestId,
                domain: $createBasekitDeploymentsFromMigrationRequest->domain
            );

            $this->basekitSitebuilderDeploymentRepository->create(
                sitebuilderDeployment: $sitebuilderDeployment,
                siteReference: $createBasekitDeploymentsFromMigrationRequest->siteRef
            );

            DB::commit();
        } catch (Exception $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            DB::rollBack();

            $this->logger->warning(
                'Provisioning Basekit sitebuilder deployment from migration failed at database',
                LogContextBuilder::for($createBasekitDeploymentsFromMigrationRequest)
                    ->withException($exception)
                    ->with(LoggingContextKeys::PROVISIONING_CONTEXT, $basekitContext)
                    ->build()
            );

            return new SitebuilderResult(
                provisionData: $createBasekitDeploymentsFromMigrationRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        return new SitebuilderResult(
            provisionData: $createBasekitDeploymentsFromMigrationRequest,
            provisionStatus: ProvisionStatus::SUCCESS
        );
    }

    private function createUser(CreateSitebuilderRequest $request): int
    {
        $username = sprintf('%s-%s', $request->domain, $this->usernameGenerator->generatePassword(10));
        $password = $this->passwordGenerator->generatePassword(35);

        try {
            $accountHolder = $this->baseKitClient->userApi->create(
                $this->config->brandReference,
                $request->firstname,
                $request->lastname,
                $username,
                $password,
                $request->email,
                self::LOCALE,
            );
        } catch (BaseKitClientException | UnexpectedValueException $exception) {
            $this->logger->error(
                sprintf(
                    'Failed to create sitebuilder user for context %s',
                    $request->context->toString(),
                ),
                LogContextBuilder::for($request)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $request->domain)
                    ->build()
            );

            throw new BasekitUserCreateException(
                context: $request->context,
                previous: $exception
            );
        }

        return $accountHolder->ref;
    }

    private function addUserPackage(
        CreateSitebuilderRequest $request,
        int $userReference,
        int $packageRef,
    ): void {
        try {
            $this->baseKitClient->packageApi->addUserPackage(
                $userReference,
                $packageRef,
                $request->contractPeriod
            );
        } catch (BaseKitClientException | UnexpectedValueException $exception) {
            $this->logger->error(
                sprintf(
                    'Failed to add package %d for user %d',
                    $packageRef,
                    $userReference
                ),
                LogContextBuilder::for($request)
                    ->withException($exception)
                    ->withMeta([
                        'basekit_package_ref' => $packageRef,
                        'basekit_user_ref' => $userReference,
                        'basekit_subscription_period' => $request->contractPeriod,
                    ])
                    ->build()
            );
            throw new BasekitAddPackageException(
                packageReference: $packageRef,
                userReference: $userReference,
                previous: $exception
            );
        }
    }

    private function createExternalSite(CreateSitebuilderRequest $request, int $userReference): Site
    {
        try {
            $site = $this->baseKitClient->sitesApi->create(
                $userReference,
                $this->config->brandReference,
                $request->domain,
            );
        } catch (BaseKitClientException | UnexpectedValueException $exception) {
            $this->logger->error(
                sprintf(
                    'Failed to create site "%s" for user %d',
                    $request->domain,
                    $userReference
                ),
                LogContextBuilder::for($request)
                    ->withException($exception)
                    ->with(LoggingContextKeys::DOMAIN_NAME, $request->domain)
                    ->withMeta([
                        'basekit_user_ref' => $userReference,
                    ])
                    ->build()
            );
            throw new BasekitCreateSiteException(
                domain: $request->domain,
                userReference: $userReference,
                previous: $exception,
            );
        }

        return $site;
    }
}
