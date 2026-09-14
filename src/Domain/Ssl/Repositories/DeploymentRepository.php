<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use RealtimeRegister\Domain\Certificate;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as HostingResult;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;

class DeploymentRepository
{
    public function __construct(
        private readonly PublicSuffixList $publicSuffixList,
    ) {
    }

    /**
     * @param mixed[] $data
     */
    public function updateOrCreate(array $data, string $subscriptionUuid, bool $customCsr): SslDeployment
    {
        return SslDeployment::updateOrCreate(
            [
                'subscription_uuid' => $subscriptionUuid,
            ],
            array_merge(
                $data,
                ['custom_csr' => $customCsr],
            ),
        );
    }

    public function findByRequestIdWithTrashed(string $requestId): SslDeployment
    {
        return SslDeployment::where([
            'request_id' => $requestId,
        ])->withTrashed()->firstOrFail();
    }

    /**
     * @return Collection<int, SslDeployment>
     */
    public function getPendingRtrDeploymentCandidatesByDomain(string $domain): Collection
    {
        return SslDeployment::query()
            ->whereNull('certificate_id')
            ->whereRelation('provider', 'slug', ProviderSlug::REALTIME_REGISTER)
            ->whereHas('subscription', fn (Builder $query): Builder => $query
                ->whereProductGroupType(ProductGroupType::SSL)
                ->where('domain', $domain)
                ->where('technical_status', TechnicalStatus::PENDING->value))
            ->with(['provider', 'subscription'])
            ->get();
    }

    public function getRelatedHostingSubscriptionForSslDeployment(SslDeployment $sslDeployment): ?HostingDeployment
    {
        $domain = $sslDeployment->subscription->domain;

        if ($domain === null) {
            return null;
        }

        $domain = $this->publicSuffixList->getRegistrableDomain($domain);

        if ($domain === null) {
            return null;
        }

        $baseHostingSubscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->where('domain', $domain)
            ->where('customer_id', $sslDeployment->subscription->customer_id)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->where('technical_status', '<>', HostingResult::STATUS_DELETED)
            ->first();

        if ($baseHostingSubscription === null) {
            return null;
        }

        $hostingDeployment = $baseHostingSubscription->hostingDeployment;
        if ($hostingDeployment === null) {
            throw new ModelNotFoundException(
                "Unable to find hosting deployment for {$sslDeployment->subscription->domain}",
            );
        }

        return $hostingDeployment;
    }

    /**
     * @return Collection<int,SslDeployment>
     */
    public function getReminderCandidates(int $days = 7): Collection
    {
        $from = CarbonImmutable::now()->startOfDay();
        $to = CarbonImmutable::now()->addDays($days)->endOfDay();

        return SslDeployment::whereHas('subscription', static function (Builder $query) use ($from, $to): void {
            $query->whereNotIn(
                'administrative_status',
                AdministrativeStatus::administrativelyEnded(),
            )->whereBetween('end_date', [$from, $to]);
        })
            ->with([
                'subscription:id,uuid,customer_id,end_date',
                'subscription.customer:id,uuid',
            ])
            ->orderBy('id')
            ->get(['id', 'subscription_uuid']);
    }

    public function findForReminderById(int $deploymentId): ?SslDeployment
    {
        return SslDeployment::where('id', $deploymentId)
            ->whereHas('subscription', function (Builder $query) {
                $query->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded());
            })
            ->with(['subscription.customer'])
            ->first();
    }

    public function getExpireDateBackfillCandidates(): Builder
    {
        return SslDeployment::query()
            ->whereNull('expire_date')
            ->whereHas('subscription', static function (Builder $query): void {
                $query->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded());
            })
            ->whereHas('provider', static function (Builder $query): void {
                $query->whereNot('slug', ProviderSlug::PLACEHOLDER);
            });
    }

    /**
     * @return Collection<int, SslDeployment>
     */
    public function getExpiringSslDeployments(int $days = 7, int $gracePeriodDays = 7): Collection
    {
        $from = CarbonImmutable::now()->startOfDay();
        $to = CarbonImmutable::now()->addDays($days)->endOfDay();
        $graceEnd = CarbonImmutable::now()->addDays($gracePeriodDays)->endOfDay();

        return SslDeployment::query()
            ->whereNotNull('expire_date')
            ->whereBetween('expire_date', [$from, $to])
            ->whereHas('provider', function (Builder $query) {
                $query->where('slug', '!=', ProviderSlug::PLACEHOLDER);
            })
            ->whereHas('subscription', function (Builder $query) use ($graceEnd) {
                $query->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded());
                $query->where(function (Builder $q) use ($graceEnd) {
                    $q->where(
                        'administrative_status',
                        '!=',
                        AdministrativeStatus::CANCELED,
                    )->orWhere(function (Builder $qq) use ($graceEnd) {
                        $qq
                            ->where('administrative_status', AdministrativeStatus::CANCELED)
                            ->whereNotNull('end_date')
                            ->where('end_date', '>', $graceEnd);
                    });
                });
            })
            ->with(['subscription'])
            ->get();
    }

    public function backfillCertificateId(int $sslDeploymentId, int $certificateId): bool
    {
        return (bool) SslDeployment::query()
            ->whereKey($sslDeploymentId)
            ->whereNull('certificate_id')
            ->update(['certificate_id' => $certificateId]);
    }

    public function linkExistingCertificate(SslDeployment $sslDeployment, Certificate $certificate): void
    {
        $sslDeployment->certificate_id = $certificate->id;
        $sslDeployment->expire_date = CarbonImmutable::createFromMutable($certificate->expiryDate);
        $sslDeployment->save();

        $subscription = $sslDeployment->subscription;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }
}
