<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\DnsPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Dns\StoreRequest;
use Waterfront\Apps\API\Waterfront\Requests\Dns\UpdateRequest;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Transformers\DnsRecordResource;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Redirects\Actions\DeleteRedirectForDomainAction;
use Waterfront\Domain\Redirects\Mappers\RedirectDnsSubscriptionMapper;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class DnsController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly DnsPolicy $dnsPolicy,
        private readonly TranslatorInterface $translator,
        private readonly DeleteRedirectForDomainAction $deleteRedirectForDomain,
        private readonly DnsService $dnsService,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly RedirectDnsSubscriptionMapper $redirectDnsMapper,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function index(string $domain): JsonResponse|AnonymousResourceCollection
    {
        $dnsSubscription = $this->getSubscriptionByDomain($domain);

        $this->dnsPolicy->assertCanManageDns($dnsSubscription);

        try {
            $records = $this->dnsService->getDnsRecordsForDomain($domain);
        } catch (DnsZoneNotFoundException|GuzzleException|JsonException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-zone-not-exists'),
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $redirectSubscription = $this->subscriptionRepository->getSubscriptionByDomainAndGroup(
                $domain,
                ProductGroupType::REDIRECT,
            );

            $mappedRecords = $this->redirectDnsMapper->addSubscriptionUuidToDnsRecords(
                dnsRecords: $records,
                subscriptionUuid: Uuid::fromString($redirectSubscription->uuid),
            );
        } catch (ModelNotFoundException) {
            $mappedRecords = $records;
        }

        return DnsRecordResource::collection($mappedRecords);
    }

    /**getDnsRecordsForDomain
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function store(StoreRequest $request, string $domain): JsonResponse
    {
        $subscription = $this->getSubscriptionByDomain($domain);
        $this->dnsPolicy->assertCanManageDns($subscription);
        $this->dnsPolicy->assertCanEditDnsRecords($subscription->product);

        try {
            $this->dnsService->addRecordFromArray($domain, $request->all());

            if ($this->dnsProductSpecRepository->isPremiumDns($subscription->product)) {
                $this->dnsService->sendNotify($domain);
            }
        } catch (ValidationException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-operation-failed'),
                    'errors' => [
                        'content' => [
                            $this->translator->translate('dns.dns-operation-failed'),
                        ],
                    ],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (JsonException|PdnsResponseException|DnsZoneNotFoundException|GuzzleException $exception) {
            Log::error(
                self::class . '::store',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-operation-failed'),
                    'errors' => [
                        'content' => [
                            $this->translator->translate('dns.dns-operation-failed'),
                        ],
                    ],
                ],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return new JsonResponse(['message' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function update(UpdateRequest $request, string $domain): JsonResponse
    {
        $subscription = $this->getSubscriptionByDomain($domain);
        $this->dnsPolicy->assertCanManageDns($subscription);
        $this->dnsPolicy->assertCanEditDnsRecords($subscription->product);

        try {
            /** @var string[] $oldRecord */
            $oldRecord = (array) $request->input('old');

            /** @var string[] $newRecord */
            $newRecord = (array) $request->input('new');

            $this->deleteRedirectForDomain->execute($subscription, $oldRecord, $newRecord);

            $this->dnsService->prepareUpdateRecord($domain, $oldRecord, $newRecord);

            if ($this->dnsProductSpecRepository->isPremiumDns($subscription->product)) {
                $this->dnsService->sendNotify($domain);
            }
        } catch (ValidationException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-operation-failed'),
                    'errors' => [
                        'content' => [
                            $this->translator->translate('dns.dns-operation-failed'),
                        ],
                    ],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (DnsZoneNotFoundException|JsonException|GuzzleException|PdnsResponseException $exception) {
            Log::error(
                self::class . '::update',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-operation-failed'),
                    'errors' => [
                        'content' => [
                            $this->translator->translate('dns.dns-operation-failed'),
                        ],
                    ],
                ],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return new JsonResponse(['message' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function destroy(Request $request, string $domain): JsonResponse
    {
        $subscription = $this->getSubscriptionByDomain($domain);
        $this->dnsPolicy->assertCanManageDns($subscription);
        $this->dnsPolicy->assertCanEditDnsRecords($subscription->product);

        try {
            /** @var string[] $oldRecord */
            $oldRecord = (array) $request->input('old');

            $this->deleteRedirectForDomain->execute($subscription, $oldRecord);

            $this->dnsService->deleteRecordFromArray($domain, $request->all());

            if ($this->dnsProductSpecRepository->isPremiumDns($subscription->product)) {
                $this->dnsService->sendNotify($domain);
            }
        } catch (
            JsonException|PdnsResponseException|DnsZoneNotFoundException|GuzzleException|ValidationException $exception
        ) {
            Log::error(
                self::class . '::store',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('dns.dns-operation-failed'),
                ],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return new JsonResponse(['message' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthorizationException
     */
    private function getSubscriptionByDomain(string $domain): Subscription
    {
        /** @var Subscription|null $subscription */
        $subscription = $this->subscriptionService
            ->getSubscriptionsQuery()
            ->where('domain', $domain)
            ->whereProductGroupType(ProductGroupType::DNS)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->first();

        if ($subscription === null) {
            throw new AuthorizationException();
        }

        return $subscription;
    }
}
