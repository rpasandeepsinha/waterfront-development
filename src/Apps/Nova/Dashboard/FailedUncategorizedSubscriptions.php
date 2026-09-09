<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Dashboard;

use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Nova;
use Waterfront\Apps\Nova\Subscriptions\Lenses\UncategorizedFailedSubscriptionsLens;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class FailedUncategorizedSubscriptions extends HtmlCard
{
    public function __construct(?string $component = null)
    {
        parent::__construct($component);
        $this->center();
        $this->html($this->createFailedSubscriptionHtmlAnchor());
    }

    private function createFailedSubscriptionHtmlAnchor(): string
    {
        $failedSubscriptionAmount = $this->countTechnicallyFailedSubscriptionsWithoutCategory();

        $url = sprintf(
            '%s/resources/%s/lens/%s',
            Nova::path(),
            NovaSubscriptionResource::uriKey(),
            new UncategorizedFailedSubscriptionsLens()->uriKey(),
        );
        return <<<HTML
<a class="no-underline dim text-primary font-bold" href="{$url}">{$failedSubscriptionAmount} (Failed) uncategorized Subscriptions</a><br />
<i>subscriptions without a category assigned</i>
HTML;
    }

    private function countTechnicallyFailedSubscriptionsWithoutCategory(): int
    {
        return Subscription::query()->whereDoesntHave('category')
            ->whereIn('technical_status', [
                TechnicalStatus::ERROR->value,
                TechnicalStatus::REGISTRATION->value,
                DomainStatus::FAILED->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::DELETING_FAILED->value,
                TechnicalStatus::SUSPENSION_FAILED->value,
                TechnicalStatus::UNSUSPENSION_FAILED->value,
            ])->whereNotIn(
                'administrative_status',
                [
                    AdministrativeStatus::CANCELED->value,
                    AdministrativeStatus::ARCHIVING->value,
                    ...AdministrativeStatus::administrativelyEnded(),
                ]
            )
            ->count();
    }
}
