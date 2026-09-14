<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\UnauthorizedException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecordTypes;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneAlreadyCreatedException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Transformers\DnsRecordResource;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

readonly class DnsZoneController
{
    public function __construct(
        private DnsService $dnsService,
        private DnsNameserverAssigner $nameserverAssigner,
        private DnsProductSpecRepository $dnsProductSpecRepository,
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * Creates a DNS Zone for a specific domain.
     *
     * @throws AuthorizationException
     * @throws AuthenticationException
     * @throws DnsZoneNotFoundException
     */
    public function store(string $domain): JsonResponse|AnonymousResourceCollection
    {
        $domainDeployment = $this->getDomainDeployment($domain);
        $dnsSubscription = $this->subscriptionRepository->getActiveDnsSubscription($domain);

        if ($domainDeployment === null) {
            return new JsonResponse(['message' => 'Domain deployment not found.', 'errors' => []], 404);
        }

        $dnsDeployment = $dnsSubscription->dnsDeployment;

        if ($dnsDeployment !== null) {
            $this->nameserverAssigner->clear($dnsDeployment);
        }

        try {
            $nameservers = $dnsDeployment !== null ? $this->nameserverAssigner->assign($dnsDeployment) : [];

            $dnsZone = $this->dnsService->createDnsZone(
                $domain,
                nameservers: $nameservers,
            );

            if ($this->dnsProductSpecRepository->isPremiumDns($dnsSubscription->product)) {
                $this->dnsService->sendNotify($domain);
            }
        } catch (DnsZoneAlreadyCreatedException) {
            $dnsZone = $this->dnsService->getDnsZone($domain);
        }

        return DnsRecordResource::collection($this->filterDnsZone($dnsZone));
    }

    public function delete(string $domain): Response
    {
        $this->dnsService->deleteZone($domain);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     */
    private function getDomainDeployment(string $domainName): ?DomainDeployment
    {
        return DomainDeployment::whereHas(
            'subscription',
            fn (Builder $query) => $query->where('domain', $domainName)->whereNotIn(
                'administrative_status',
                AdministrativeStatus::administrativelyEnded(),
            ),
        )->first();
    }

    /**
     * @return Collection<int, DnsRecordInterface>
     */
    private function filterDnsZone(DnsZone $dnsZone): Collection
    {
        $modifiableTypes = DnsRecordTypes::getModifiable();

        return new Collection(
            array_filter(
                $dnsZone->getRecords(),
                fn (DnsRecordInterface $dnsRecord): bool => in_array($dnsRecord->getType(), $modifiableTypes, true),
            ),
        );
    }
}
