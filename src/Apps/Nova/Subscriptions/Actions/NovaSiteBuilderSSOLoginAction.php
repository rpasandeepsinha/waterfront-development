<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSiteBuilderSSOLoginAction extends NovaSubscriptionAction
{
    public $withoutConfirmation = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SitebuilderService $sitebuilderService,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.sitebuilder_sso_login');
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @throws ServerNotFoundException
     * @throws ItemNotFoundException
     */
    public function handle(ActionFields $fields, Collection $subscriptions): ActionResponse|static
    {
        $subscription = $subscriptions->firstOrFail();

        if (! $subscription->product->isSitebuilderProduct()) {
            return self::message($this->translator->translate('nova-action.error.no_sitebuilder_subscriptioon'));
        }

        if (null === $subscription->hostingDeployment) {
            return self::message($this->translator->translate('nova-action.error.no_hosting_deployment_found'));
        }

        $ssoUrl = $this->sitebuilderService->getSsoUrl($subscription->hostingDeployment);

        return self::openInNewTab($ssoUrl);
    }
}
