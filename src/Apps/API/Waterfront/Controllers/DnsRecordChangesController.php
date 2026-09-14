<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Waterfront\Apps\API\Waterfront\Policies\DnsPolicy;
use Waterfront\Apps\API\Waterfront\Resources\DnsRecordChangeResource;
use Waterfront\Domain\DNS\Repository\DnsRecordChangeRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class DnsRecordChangesController
{
    public function __construct(
        private readonly DnsPolicy $dnsPolicy,
        private readonly DnsRecordChangeRepository $dnsRecordChangeRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    public function index(Subscription $subscription): JsonResponse|AnonymousResourceCollection
    {
        $this->dnsPolicy->assertCanManageDns($subscription);

        $dnsSubscription = $this->subscriptionRepository->getActiveDnsSubscription((string) $subscription->domain);

        $visibleLogsProductSpec = $this->productSpecRepository->findBySpecification(
            $dnsSubscription->product,
            ProductSpecName::DNS_VISIBLE_LOG_LINES->value,
        );
        $visibleLogs = (int) $visibleLogsProductSpec?->value;

        $logs = $this->dnsRecordChangeRepository->getBySubscription($dnsSubscription, $visibleLogs);

        return DnsRecordChangeResource::collection($logs);
    }
}
