<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\DNS\Actions;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Jobs\ResetDnsTemplateJob;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Redirects\Exceptions\ListRedirectsException;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\RedirectDnsServiceInterface;
use Waterfront\Domain\Redirects\Services\RedirectServiceInterface;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaResetDnsTemplateAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $jobDispatcher,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
        private readonly DnsService $dnsService,
        private readonly StoreNoteAction $storeNoteAction,
        private readonly RedirectServiceInterface $redirectService,
        private readonly RedirectDnsServiceInterface $redirectDnsService,
        private readonly PublicSuffixList $publicSuffixList,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => (
                $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::DNS)
                || $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::REDIRECT)
            ),
        );

        $this->confirmText('');
        $this->modalSize = '4xl';
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.reset_dns');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|Action
    {
        if ($fields->warning) {
            return self::danger($this->translator->translate('nova-action.reset-dns-template.error.no-dns-zone-found'));
        }

        if (! $fields->confirm_action) {
            return self::danger(
                $this->translator->translate('nova-action.confirmation_checkbox_error'),
            );
        }

        $subscription = $models->firstOrFail();

        if ($subscription->domain === null) {
            return self::danger($this->translator->translate('nova-action.error.no_domain_found'));
        }

        $productGroupType = $subscription->product->productGroup->slug;

        return match ($productGroupType) {
            ProductGroupType::DNS => $this->handleHostingReset($subscription),
            ProductGroupType::REDIRECT => $this->handleRedirectReset($subscription),
            default => self::danger($this->translator->translate(
                'nova-action.reset-dns.error.unsupported-subscription-type',
            )),
        };
    }

    /**
     * @return array<int, Heading|Hidden|NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        /** @var Collection<int, Subscription> $selectedResource */
        $selectedResource = $request->selectedResources();
        $subscription = $selectedResource->firstOrFail();

        $recordsCannotBeRetrieved = $this->translator->translate(
            'nova-action.reset-dns-template.error.records-cannot-be-retrieved',
        );
        $recordsStillVisibleInLogs = $this->translator->translate(
            'nova-action.reset-dns-template.info.deleted-records-visible',
        );
        $followingRecordsWillBeDeleted = $this->translator->translate(
            'nova-action.reset-dns-template.info.following-records-deleted',
        );

        try {
            Assert::string($subscription->domain);
            $records = $this->dnsService->getDnsRecordsForDomain($subscription->domain);
        } catch (Exception) { // @phpstan-ignore-line
            return [
                Heading::make('warning')
                    ->withMeta([
                        'value' => "<p>{$recordsCannotBeRetrieved}</p><p class='py-4'>{$recordsStillVisibleInLogs}</p>",
                    ])
                    ->asHtml(),
                Hidden::make('warning')->default(true),
            ];
        }

        $table = "<h2 class='text-xl mb-5'>{$followingRecordsWillBeDeleted}</h2><table class='w-full divide-y divide-gray-100 dark:divide-gray-700 mb-5'><tr class='text-left whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide border-b border-blue-gray-100 bg-blue-gray-50'><th class='py-2'>Type</th><th class='py-2'>Name</th><th class='py-2'>Value</th><th class='py-2'>TTL</th></tr>";

        foreach ($records as $zone) {
            $table .= "<tr><td class='py-2'>{$zone->getType()}</td><td class='py-2'>{$zone->getName()}</td><td class='py-2'>{$zone->getContent()}</td><td class='py-2'>{$zone->getTtl()}</td></tr>";
        }

        $table .= "</table><p>{$recordsStillVisibleInLogs}</p>";

        return [
            Heading::make('info')->withMeta(['value' => $table])->asHtml(),
            NovaBoolField::make(
                $this->translator->translate('confirm.title'),
                'confirm_action',
            )->help($this->translator->translate('nova-action.subscription.resume.execute.confirmation_checkbox')),
        ];
    }

    private function handleHostingReset(Subscription $subscription): ActionResponse|Action
    {
        Assert::string($subscription->domain);

        $dnsDeployment = $subscription->dnsDeployment;

        if (! $dnsDeployment instanceof DnsDeployment) {
            return self::danger($this->translator->translate('nova-action.error.no_dns_deployment_found'));
        }

        $domainSubscription = $subscription->parent;

        if (! $domainSubscription instanceof Subscription) {
            return self::danger($this->translator->translate('nova-action.error.no_domain_subscription_found'));
        }

        $domainDeployment = $domainSubscription->domainDeployment;

        if (! $domainDeployment instanceof DomainDeployment) {
            return self::danger($this->translator->translate('nova-action.error.no_domain_deployment_found'));
        }

        try {
            $hostingSubscription = $this->hostingDeploymentRepository->getByDomain($subscription->domain);
        } catch (ModelNotFoundException) {
            return self::danger($this->translator->translate('nova-action.error.no_hosting_subscription_found'));
        }

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        if ($hostingDeployment === null) {
            return self::danger($this->translator->translate('nova-action.error.no_hosting_deployment_found'));
        }

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);

        if ($server === null) {
            return self::danger($this->translator->translate('nova-action.error.hosting_deployment_without_server'));
        }

        $this->storeNoteAction->execute(
            sprintf('DNS reset started for %s', $subscription->domain),
            $subscription,
        );

        $this->jobDispatcher->dispatch(new ResetDnsTemplateJob(
            $dnsDeployment,
            $hostingDeployment,
        ));

        return Action::message($this->translator->translate('nova-action.success.reset_dns_template'));
    }

    private function handleRedirectReset(Subscription $subscription): ActionResponse|Action
    {
        Assert::string($subscription->domain);

        try {
            $redirects = $this->redirectService->listRedirects($subscription);
        } catch (ListRedirectsException) {
            return self::danger($this->translator->translate('nova-action.reset-dns.error.redirect-list-failed'));
        }

        $this->storeNoteAction->execute(
            sprintf('DNS reset for redirects started for %s', $subscription->domain),
            $subscription,
        );

        foreach ($redirects as $redirect) {
            $redirectSourceHost = $this->publicSuffixList->getHostFromUrlOrDomain($redirect['source']);

            if ($redirectSourceHost === null) {
                continue;
            }

            $baseDomain = $this->publicSuffixList->getRegistrableDomain($redirectSourceHost);

            if ($baseDomain === null) {
                continue;
            }

            $this->redirectDnsService->provisionDnsRecords(
                $baseDomain,
                $redirectSourceHost,
                DnsRedirectProvisionOption::OVERRIDE,
            );
        }

        return Action::message($this->translator->translate('nova-action.success.reset_dns_template'));
    }
}
