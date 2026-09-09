<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Dashboard;

use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Nova;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaFailedSubscriptionsFilter;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class FailedDeployments extends HtmlCard
{
    public function __construct(?string $component = null)
    {
        parent::__construct($component);
        $this->center();
        $this->html($this->createFailedSubscriptionHtmlAnchor());
    }

    private function createFailedSubscriptionHtmlAnchor(): string
    {
        $json = json_encode([
            [
                'class' => NovaFailedSubscriptionsFilter::class,
                'value' => 'technical_status',
            ],
        ]);

        $filters = base64_encode(strval($json));

        $failedSubscriptionAmount = $this->countTechnicallyFailedSubscriptions();

        $url = sprintf(
            '%s/resources/%s?%s_page=1&%s_filter=%s',
            Nova::path(),
            NovaSubscriptionResource::uriKey(),
            NovaSubscriptionResource::uriKey(),
            NovaSubscriptionResource::uriKey(),
            $filters
        );
        return <<<HTML
<a class="no-underline dim text-primary font-bold" href="{$url}">{$failedSubscriptionAmount} Failed Subscriptions</a><br />
<i>subscriptions with a deployment error</i>
HTML;
    }

    private function countTechnicallyFailedSubscriptions(): int
    {
        return Subscription::query()
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
