<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\ResellerHosting\Jobs\CreateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaRetryHostingAction extends NovaSubscriptionAction
{
    public const HOSTING_OPTIONS = [
        'basic' => 'basic',
        ProductType::MAIL_ONLY->value => 'mail_only',
        ProductType::SITEBUILDER->value => 'sitebuilder',
        'reseller-hosting' => 'Reseller hosting',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcher $eventDispatcher,
        private readonly JobDispatcher $jobDispatcher,
        private readonly LoggerInterface $logger,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => (
                $this->onlyForSingleSubscription($request)
                && $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::HOSTING)
            ),
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry_hosting');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->first();

        Assert::isInstanceOf($subscription, Subscription::class, 'Only Base Subscriptions allowed');

        if (
            $subscription->product->productGroup->slug !== ProductGroupType::HOSTING
            && $subscription->product->productGroup->slug !== ProductGroupType::RESELLER_HOSTING
        ) {
            return self::danger($this->translator->translate('nova-action.error.subscription_invalid_for_retry'));
        }

        /** @var string $type */
        $type = Arr::get($fields, 'hosting_options');

        /** @var string|null $serverId */
        $serverId = Arr::get($fields, 'server_id');

        if ($serverId !== null) {
            $serverId = (int) $serverId;
        }

        match ($type) {
            'basic' => $this->handleBasicHosting($subscription, $serverId),
            ProductType::SITEBUILDER->value => $this->handleSiteBuilderHosting($subscription),
            ProductType::MAIL_ONLY->value => $this->handleMailOnlyHosting($subscription),
            'reseller-hosting' => $this->handleResellerHosting($subscription, $serverId),
            default => null,
        };

        return self::message($this->translator->translate('nova-action.success.retried_hosting'));
    }

    /**
     * @return array<int, Select|Text|Number>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make($this->translator->translate('nova-action.hosting_options'), 'hosting_options')
                ->options(self::HOSTING_OPTIONS)
                ->default('basic'),
            Number::make($this->translator->translate('nova-action.server_id'), 'server_id'),
        ];
    }

    private function handleBasicHosting(Subscription $subscription, ?int $serverId): void
    {
        $domain = $subscription->domain;

        if ($subscription->technical_status === TechnicalStatus::ERROR->value) {
            $this->logger->error(
                'Deleting hosting deployment',
                [
                    LoggingContextKeys::SERVER_ID => $subscription->hostingDeployment?->server_id,
                    LoggingContextKeys::PROVISIONING_PROVIDER =>
                        $subscription->hostingDeployment?->provider?->slug->value,
                    LoggingContextKeys::META => [
                        'sitebuilder_provider_id' => $subscription->hostingDeployment?->sitebuilder_provider_id,
                        'mail_only_provider_id' => $subscription->hostingDeployment?->mail_only_provider_id,
                        'basekit_user_ref' => $subscription->hostingDeployment?->basekit_user_ref,
                        'basekit_site_ref' => $subscription->hostingDeployment?->basekit_site_ref,
                        'basekit_server_id' => $subscription->hostingDeployment?->basekit_server_id,
                        'directadmin_customer_username' =>
                            $subscription->hostingDeployment?->directadmin_customer_username,
                        'plesk_customer_id' => $subscription->hostingDeployment?->plesk_customer_id,
                        'plesk_customer_username' => $subscription->hostingDeployment?->plesk_customer_username,
                    ],
                ],
            );
            $subscription->hostingDeployment?->forceDelete();
        }

        $this->eventDispatcher->dispatch(
            new CreateHosting(
                $subscription->uuid,
                $subscription->technical_status,
                $subscription->customer->name,
                $subscription->customer->email,
                $domain,
                $subscription->customer,
                $subscription->product,
                $serverId,
            ),
        );
    }

    private function handleSiteBuilderHosting(Subscription $subscription): void
    {
        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->eventDispatcher->dispatch(new CreateSitebuilder(
            $subscription->customer->name,
            $subscription->customer->email,
            $subscription,
        ));
    }

    private function handleMailOnlyHosting(Subscription $subscription): void
    {
        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->eventDispatcher->dispatch(new CreateMailOnlyHosting(
            $subscription->customer->name,
            $subscription->customer->email,
            $subscription,
        ));
    }

    private function handleResellerHosting(Subscription $subscription, ?int $serverId): void
    {
        $this->jobDispatcher->dispatch(
            new CreateResellerHostingJob(
                subscriptionUuid: $subscription->uuid,
                technicalStatus: $subscription->technical_status,
                contactPersonName: $subscription->customer->name,
                contactEmail: $subscription->customer->email,
                serverId: $serverId,
                customer: $subscription->customer,
                product: $subscription->product,
            ),
        );
    }
}
