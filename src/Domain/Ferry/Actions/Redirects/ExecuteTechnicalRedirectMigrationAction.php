<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Redirects;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Ferry\Jobs\TechnicalRedirectMigrationJob;
use Waterfront\Domain\Ferry\Mappers\RedirectMapper;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExecuteTechnicalRedirectMigrationAction
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly RedirectMapper $redirectMapper,
    ) {
    }

    /**
     * @param array<int, array<string, string>> $technicalPayloads
     * @param Collection<int, Subscription>     $subscriptions
     */
    public function execute(Collection $subscriptions, array $technicalPayloads): void
    {
        $mappedPayloads = $this->redirectMapper->mapSubscriptionsWithRedirects($subscriptions, $technicalPayloads);

        foreach ($mappedPayloads as $mappedPayload) {
            $subscription = $mappedPayload->subscription;

            $subscription->technical_status = TechnicalStatus::PENDING->value;
            $subscription->save();

            $this->jobDispatcher->dispatch(new TechnicalRedirectMigrationJob(
                subscription: $subscription,
                failedTechnicalStatus: TechnicalStatus::FAILED->value,
                redirectTechnicalPayload: $mappedPayload->redirectTechnicalPayload,
            ));
        }
    }
}
