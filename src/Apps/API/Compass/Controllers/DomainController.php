<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use JsonException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Waterfront\Apps\API\Compass\Resources\AuditLogs\AuditLogResource;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainContactResource;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainNameserversResource;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainProcessResource;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainRevisionResource;
use Waterfront\Domain\AuditLogs\Actions\FetchAuditLogsForDomainAction;
use Waterfront\Domain\DNS\Actions\RedeployDnsAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Transformers\DnsRecordResource;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\RevisionInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\PowerDnsClient\Clients\RawPowerDnsRetriever;
use Waterfront\Infra\RtrClient\Exceptions\RtrApiException;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DomainController
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly DnsService $dnsService,
        private readonly FetchAuditLogsForDomainAction $fetchAuditLogsForDomainAction,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly RawPowerDnsRetriever $rawPowerDnsRetriever,
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchDnsZoneFromSource(string $domain): JsonResponse
    {
        $metadata = $this->dnsService->getMetadata($domain);

        return new JsonResponse([
            'pdns_version' => $this->rawPowerDnsRetriever->getPowerDnsVersion(),
            'rrsets' => $this->rawPowerDnsRetriever->getPowerDnsZoneResponseBody($domain),
            'metadata' => $metadata,
        ]);
    }

    public function contact(string $domain): string
    {
        $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);

        $contactOwner = $subscription->domainDeployment?->contactOwner;

        return DomainContactResource::make($contactOwner)->toJson();
    }

    public function nameservers(string $domain): string
    {
        $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $subscription->domainDeployment;

        try {
            $result = $this->domainService->checkNameservers($domainDeployment);
        } catch (RealtimeRegisterClientException | DomainDoesNotExistException) {
            $result = [];
        }

        return DomainNameserversResource::make($result)->toJson();
    }

    public function dns(string $domain): string|JsonResponse
    {
        try {
            $records = $this->dnsService->getDnsRecordsForDomain($domain);
        } catch (DnsZoneNotFoundException | GuzzleException | JsonException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-zone-not-exists'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return DnsRecordResource::collection($records)->toJson();
    }

    public function redeployDns(string $domain, RedeployDnsAction $redeployDnsAction): Response
    {
        $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::DNS);
        $redeployDnsAction->execute($subscription);
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function processes(string $domain): JsonResponse|AnonymousResourceCollection
    {
        $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);

        $domainDeployment = $subscription->domainDeployment;
        if ($domainDeployment === null) {
            return new JsonResponse(['message' => 'The deployment could not be found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($domainDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            return new JsonResponse(['message' => 'Domain processes are not supported for this provider'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rtrService = $this->domainServiceFactory->driver($domainDeployment->provider->slug, $domainDeployment->businessUnit);
        Assert::isInstanceOf($rtrService, RtrService::class);

        try {
            $processes = $rtrService->listProcessesForDomain($domain);
        } catch (RtrApiException $exception) {
            $this->logger->error('Unable to fetch domain processes from RTR', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProviderSlug::REALTIME_REGISTER->value,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('domain-processes.failed-to-fetch'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return DomainProcessResource::collection($processes->entities);
    }

    public function revisions(string $domain): JsonResponse|AnonymousResourceCollection
    {
        try {
            $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => $this->translator->translate('domain-revisions.domain-not-found')], Response::HTTP_NOT_FOUND);
        }

        $domainDeployment = $subscription->domainDeployment;
        if ($domainDeployment === null) {
            return new JsonResponse(['message' => $this->translator->translate('domain-revisions.deployment-not-found')], Response::HTTP_NOT_FOUND);
        }

        $domainService = $this->domainServiceFactory->driver($domainDeployment->provider->slug, $domainDeployment->businessUnit);

        if (! $domainService instanceof RevisionInterface) {
            return new JsonResponse(['message' => $this->translator->translate('domain-revisions.provider-not-supported')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $revisions = $domainService->revisions($domain);
        } catch (RtrApiException $exception) {
            $this->logger->error('Unable to fetch domain revisions from RTR', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProviderSlug::REALTIME_REGISTER->value,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('domain-revisions.failed-to-fetch'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return DomainRevisionResource::collection($revisions);
    }

    public function auditLogs(Request $request, string $domain): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $auditLogPaginator = $this->fetchAuditLogsForDomainAction->execute($domain, $pageSize);
        $auditLogPaginator->appends('pageSize', (string) $pageSize);
        return AuditLogResource::collection($auditLogPaginator);
    }
}
