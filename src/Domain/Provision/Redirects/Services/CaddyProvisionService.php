<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Services;

use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\SaloonException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Redirects\Exceptions\CaddyIdNotFoundException;
use Waterfront\Domain\Provision\Redirects\Exceptions\CaddyMapperException;
use Waterfront\Domain\Provision\Redirects\Interfaces\RedirectProvisionServiceInterface;
use Waterfront\Domain\Provision\Redirects\Repositories\CaddyRedirectDeploymentRepository;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectContextRepository;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectDeploymentRepository;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Infra\CaddyClient\CaddyClient;

class CaddyProvisionService implements RedirectProvisionServiceInterface
{
    public function __construct(
        private readonly CaddyClient $caddyClient,
        private readonly LoggerInterface $logger,
        private readonly RedirectDeploymentRepository $redirectDeploymentRepository,
        private readonly RedirectContextRepository $redirectContextRepository,
        private readonly CaddyRedirectDeploymentRepository $caddyRedirectDeploymentRepository,
        private readonly CaddyProvisionClientMapper $caddyProvisionClientMapper,
    ) {
    }

    public function createRedirect(CreateRedirectRequest $provisionData): RedirectResult
    {
        $logContext = LogContextBuilder::for($provisionData)->build();

        $this->logger->info(
            sprintf(
                'Create %s redirect from %s to %s',
                $provisionData->redirectType->value,
                $provisionData->domain,
                $provisionData->destinationUrl,
            ),
            $logContext,
        );
        $host = $provisionData->domain;
        if (! str_contains($host, 'https://') && ! str_contains($host, 'http://')) {
            $host = sprintf('https://%s', $host);
        }

        $parsedUrl = parse_url($host, PHP_URL_HOST);

        $host = is_string($parsedUrl) && $parsedUrl !== '' ? $parsedUrl : $provisionData->domain;

        $redirectContext = $this->redirectContextRepository->findOrCreate(
            context: $provisionData->context,
            host: $host,
        );

        $caddyRedirectType = $this->caddyProvisionClientMapper->getCaddyRedirectType($provisionData->redirectType);

        $matchers = $this->caddyProvisionClientMapper->parseSourceMatchers($provisionData->domain);

        try {
            $caddy_id = $this->caddyClient->createRedirect(
                fromHost: $matchers->host,
                toUrl: $provisionData->destinationUrl,
                redirectType: $caddyRedirectType,
                paths: $matchers->paths,
                query: $matchers->query,
            );
        } catch (SaloonException $exception) {
            $this->logger->warning(
                sprintf(
                    'Could not create %s redirect from %s to %s',
                    $provisionData->redirectType->value,
                    $provisionData->domain,
                    $provisionData->destinationUrl,
                ),
                LogContextBuilder::for($provisionData)->withException($exception)->build(),
            );

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        $redirectDeployment = $this->redirectDeploymentRepository->create(
            requestId: $provisionData->requestId,
            source: $provisionData->domain,
            destination: $provisionData->destinationUrl,
            type: $provisionData->redirectType,
            context: $redirectContext->context_uuid,
        );

        $this->caddyRedirectDeploymentRepository->create($redirectDeployment, $caddy_id);

        return new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function getRedirect(GetRedirectRequest $provisionData): GetRedirectResult
    {
        $deployment = $this->redirectDeploymentRepository->findBySourceAndContext(
            source: $provisionData->domainName,
            contextUuid: $provisionData->context,
        );

        if ($deployment === null) {
            return new GetRedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException('No deployment found for the given domain name and context'),
            );
        }

        if ($deployment->caddyRedirectDeployment === null) {
            return new GetRedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new CaddyIdNotFoundException($deployment->uuid),
            );
        }

        return $this->getRedirectResult(
            $deployment->caddyRedirectDeployment->caddy_id,
            $deployment->source,
            $provisionData,
        );
    }

    public function listRedirects(ListRedirectsRequest $provisionData): ListRedirectResult
    {
        $redirectsResults = [];
        $deployments = $this->redirectDeploymentRepository->findAllByContext($provisionData->context);
        if ($deployments->isEmpty()) {
            return new ListRedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
                redirects: [],
            );
        }

        foreach ($deployments as $deployment) {
            if ($deployment->caddyRedirectDeployment === null) {
                $redirectsResults[] = new GetRedirectResult(
                    provisionData: $provisionData,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: new CaddyIdNotFoundException($deployment->uuid),
                );

                continue;
            }

            $redirectsResults[] = $this->getRedirectResult(
                $deployment->caddyRedirectDeployment->caddy_id,
                $deployment->source,
                $provisionData,
            );
        }

        return new ListRedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            redirects: $redirectsResults,
        );
    }

    public function updateRedirect(UpdateRedirectRequest $provisionData): RedirectResult
    {
        $redirectDeployment = $this->redirectDeploymentRepository->findBySourceAndContext(
            source: $provisionData->oldSource,
            contextUuid: $provisionData->context,
        );

        if ($redirectDeployment === null) {
            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf(
                        'No redirect deployment found for the given domain [%s] and context [%s]',
                        $provisionData->oldSource,
                        $provisionData->context,
                    ),
                ),
            );
        }

        $caddyId = $redirectDeployment->caddyRedirectDeployment?->caddy_id;

        if ($caddyId === null) {
            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new CaddyIdNotFoundException($redirectDeployment->uuid),
            );
        }

        $oldDestination = $redirectDeployment->destination;
        $oldType = $redirectDeployment->type;

        $this->logger->info(
            sprintf(
                'Updating redirect for %s from [%s => %s] to [%s => %s]',
                $provisionData->newSource,
                $oldType->value,
                $oldDestination,
                $provisionData->redirectType->value,
                $provisionData->destinationUrl,
            ),
            LogContextBuilder::for($provisionData)->withMeta([
                'old_source' => $provisionData->oldSource,
                'new_source' => $provisionData->newSource,
                'caddy_id' => $caddyId,
                'old_type' => $oldType->value,
                'old_destination' => $oldDestination,
                'type' => $provisionData->redirectType->value,
                'destination' => $provisionData->destinationUrl,
            ])->build(),
        );

        $caddyRedirectType = $this->caddyProvisionClientMapper->getCaddyRedirectType($provisionData->redirectType);
        $matchers = $this->caddyProvisionClientMapper->parseSourceMatchers($provisionData->newSource);

        try {
            $caddyId = $this->caddyClient->updateRedirect(
                caddyId: $caddyId,
                fromHost: $matchers->host,
                toUrl: $provisionData->destinationUrl,
                redirectType: $caddyRedirectType,
                paths: $matchers->paths,
                query: $matchers->query,
            );
        } catch (SaloonException $exception) {
            $this->logger->warning(
                sprintf(
                    'Could not update redirect for %s from [%s => %s] to [%s => %s]',
                    $provisionData->newSource,
                    $oldType->value,
                    $oldDestination,
                    $provisionData->redirectType->value,
                    $provisionData->destinationUrl,
                ),
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta(['caddy_id' => $caddyId])
                    ->build(),
            );

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        $this->redirectDeploymentRepository->update(
            redirectDeployment: $redirectDeployment,
            requestId: $provisionData->requestId,
            source: $provisionData->newSource,
            destination: $provisionData->destinationUrl,
            type: $provisionData->redirectType,
        );

        return new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function deleteRedirect(DeleteRedirectRequest $provisionData): RedirectResult
    {
        $deployment = $this->redirectDeploymentRepository->findBySourceAndContext(
            source: $provisionData->domainName,
            contextUuid: $provisionData->context,
        );

        if ($deployment === null) {
            $this->logger->info(
                'Redirect has not been deleted because no deployment was found for the given domain name and context.',
                LogContextBuilder::for($provisionData)->withMeta([
                    'redirect' => [
                        'source' => $provisionData->domainName,
                    ],
                ])->build(),
            );

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
            );
        }

        $caddyDeployment = $deployment->caddyRedirectDeployment;
        if ($caddyDeployment === null) {
            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(sprintf(
                    'No caddy deployment found for the deployment: %s',
                    $deployment->uuid,
                )),
            );
        }

        try {
            $this->caddyClient->deleteRedirect($deployment->caddyRedirectDeployment->caddy_id);
            $this->caddyRedirectDeploymentRepository->deleteDeploymentWithParent($caddyDeployment);

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
            );
        } catch (FatalRequestException|SaloonException $exception) {
            $this->logger->error(
                sprintf('Could not retrieve redirects by key: [%s]', $deployment->caddyRedirectDeployment->caddy_id),
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta(['caddy_id' => $deployment->caddyRedirectDeployment->caddy_id])
                    ->build(),
            );

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }

    public function terminateRedirects(TerminateRedirectsRequest $provisionData): RedirectResult
    {
        $deployments = $this->redirectDeploymentRepository->findAllByContext($provisionData->context);
        if ($deployments->isEmpty()) {
            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
            );
        }

        foreach ($deployments as $deployment) {
            if ($deployment->caddyRedirectDeployment === null) {
                $this->logger->warning(
                    sprintf(
                        'No active caddy deployment found for redirect deployment [%s] during termination, deleting parent deployment only.',
                        $deployment->uuid,
                    ),
                    LogContextBuilder::for($provisionData)->withMeta([
                        'redirect_deployment_id' => $deployment->id,
                        'source' => $deployment->source,
                    ])->build(),
                );

                $deployment->delete();
                continue;
            }

            try {
                $this->caddyClient->deleteRedirect($deployment->caddyRedirectDeployment->caddy_id);
                $this->caddyRedirectDeploymentRepository->deleteDeploymentWithParent($deployment->caddyRedirectDeployment);

                $this->logger->info(
                    sprintf(
                        'Redirect with caddy id "%s" has been deleted.',
                        $deployment->caddyRedirectDeployment->caddy_id,
                    ),
                    LogContextBuilder::for($provisionData)->withMeta([
                        'caddy_id' => $deployment->caddyRedirectDeployment->caddy_id,
                    ])->build(),
                );
            } catch (FatalRequestException|SaloonException $exception) {
                $this->logger->error(
                    sprintf(
                        'Could not retrieve redirects by key: [%s]',
                        $deployment->caddyRedirectDeployment->caddy_id,
                    ),
                    LogContextBuilder::for($provisionData)
                        ->withException($exception)
                        ->withMeta(['caddy_id' => $deployment->caddyRedirectDeployment->caddy_id])
                        ->build(),
                );

                return new RedirectResult(
                    provisionData: $provisionData,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            }
        }

        $this->redirectContextRepository->deleteByContext($provisionData->context);

        return new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function suspendRedirects(SuspendRedirectRequest $provisionData): RedirectResult
    {
        $deployments = $this->redirectDeploymentRepository->findAllByContext($provisionData->context);

        if ($deployments->isEmpty()) {
            $this->logger->info(
                'No redirect deployments found for redirect suspension.',
                LogContextBuilder::for($provisionData)->build(),
            );

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
            );
        }

        foreach ($deployments as $deployment) {
            $caddyDeployment = $deployment->caddyRedirectDeployment;

            if ($caddyDeployment === null) {
                $this->logger->warning(
                    sprintf(
                        'No caddy deployment found for redirect deployment [%s] during suspension, skipping.',
                        $deployment->uuid,
                    ),
                    LogContextBuilder::for($provisionData)->build(),
                );

                continue;
            }

            try {
                $this->caddyClient->deleteRedirect($caddyDeployment->caddy_id);
                $this->caddyRedirectDeploymentRepository->forceDelete($caddyDeployment);
            } catch (FatalRequestException|SaloonException $exception) {
                $this->logger->error(
                    sprintf('Could not suspend redirect with caddy id [%s].', $caddyDeployment->caddy_id),
                    LogContextBuilder::for($provisionData)
                        ->withException($exception)
                        ->withMeta([
                            'redirect_deployment_id' => $deployment->id,
                            'source' => $deployment->source,
                            'caddy_id' => $caddyDeployment->caddy_id,
                        ])
                        ->build(),
                );

                return new RedirectResult(
                    provisionData: $provisionData,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            }
        }

        return new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function unsuspendRedirects(UnsuspendRedirectRequest $provisionData): RedirectResult
    {
        $deployments = $this->redirectDeploymentRepository->findAllByContext($provisionData->context);

        if ($deployments->isEmpty()) {
            $this->logger->info(
                'No redirect deployments found for redirect unsuspension.',
                LogContextBuilder::for($provisionData)->build(),
            );

            return new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
            );
        }

        foreach ($deployments as $deployment) {
            if ($deployment->caddyRedirectDeployment !== null) {
                continue;
            }

            $caddyRedirectType = $this->caddyProvisionClientMapper->getCaddyRedirectType($deployment->type);
            $matchers = $this->caddyProvisionClientMapper->parseSourceMatchers($deployment->source);

            try {
                $caddyId = $this->caddyClient->createRedirect(
                    fromHost: $matchers->host,
                    toUrl: $deployment->destination,
                    redirectType: $caddyRedirectType,
                    paths: $matchers->paths,
                    query: $matchers->query,
                );

                $this->caddyRedirectDeploymentRepository->create($deployment, $caddyId);

                $this->logger->info(
                    sprintf(
                        'Redirect [%s => %s] has been unsuspended with caddy id [%s].',
                        $deployment->source,
                        $deployment->destination,
                        $caddyId,
                    ),
                    LogContextBuilder::for($provisionData)->withMeta([
                        'redirect_deployment_id' => $deployment->id,
                        'source' => $deployment->source,
                        'destination' => $deployment->destination,
                        'caddy_id' => $caddyId,
                    ])->build(),
                );
            } catch (RequestException|FatalRequestException $exception) {
                $this->logger->error(
                    sprintf('Could not unsuspend redirect [%s => %s].', $deployment->source, $deployment->destination),
                    LogContextBuilder::for($provisionData)
                        ->withException($exception)
                        ->withMeta([
                            'redirect_deployment_id' => $deployment->id,
                            'source' => $deployment->source,
                            'destination' => $deployment->destination,
                        ])
                        ->build(),
                );

                return new RedirectResult(
                    provisionData: $provisionData,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            }
        }

        return new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    private function getRedirectResult(
        string $caddyId,
        string $source,
        GetRedirectRequest|ListRedirectsRequest $provisionData,
    ): GetRedirectResult {
        try {
            $redirect = $this->caddyProvisionClientMapper->getRedirectDtoFromCaddyDto($this->caddyClient->getRedirect(
                $caddyId,
            ));
            $redirect->source = $source;

            return new GetRedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
                redirect: $redirect,
            );
        } catch (ExceptionInterface|SaloonException $exception) {
            $this->logger->error(
                sprintf('Could not retrieve redirects by key: [%s]', $caddyId),
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta(['caddy_id' => $caddyId])
                    ->build(),
            );

            return new GetRedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        } catch (CaddyMapperException $exception) {
            $this->logger->error(
                'Could not map data from caddy dto to redirect dto',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta(['caddy_id' => $caddyId])
                    ->build(),
            );

            return new GetRedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }
}
