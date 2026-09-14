<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Subscriptions;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\SubscriptionCategories;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;
use Waterfront\Domain\Subscriptions\Services\ReProvisionService;
use Waterfront\Support\Exceptions\NotImplementedException;

#[AsCommand(name: 'subscriptions:process-technical-mutations')]
#[Description(
    'Subscription mutations that require technical reprovisioning due to an upgrade downgrade or specs changes',
)]
class ProcessTechnicalMutations extends AbstractCommand
{
    public function handle(SubscriptionMutationRepository $repository, ReProvisionService $reProvisionService): int
    {
        $mutations = $repository->getEligibleForTechnicalProcessing();
        $this->line('Start processing ' . $mutations->count() . ' technical mutations');

        foreach ($mutations as $mutation) {
            try {
                $reProvisionService->reProvisionSubscription($mutation->subscription, $mutation);
            } catch (NotImplementedException) {
                $mutation->subscription->technical_status = TechnicalStatus::ERROR->value;
                $mutation->subscription->save();

                $category = new SubscriptionCategories();
                $category->subscription_id = $mutation->subscription_id;
                $category->name = SubscriptionCategory::PRODUCT_CHANGE;
                $category->save();
            } finally {
                $mutation->processed_technical_at = CarbonImmutable::now();
                $mutation->save();
            }
        }

        return self::SUCCESS;
    }
}
