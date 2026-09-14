<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Waterfront\Apps\API\Compass\Resources\Dns\DnsRecordChangeResource;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

readonly class DnsRecordChangeController
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    public function index(Request $request, string $domain): JsonResponse|AnonymousResourceCollection
    {
        $dnsSubscription = $this->subscriptionRepository->getActiveDnsSubscription($domain);

        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $logBuilder = DnsRecordChange::query()
            ->where('subscription_id', $dnsSubscription->id)
            ->orderBy('created_at', 'desc');
        $logs = $logBuilder->paginate($pageSize);
        $logs->appends('pageSize', (string) $pageSize);

        return DnsRecordChangeResource::collection($logs)->additional([
            'meta' => ['totalLogs' => $logBuilder->count()],
        ]);
    }
}
