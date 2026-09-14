<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services\Templates;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Events\ZoneOutdated;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Webmozart\Assert\Assert;

class TemplateService
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly SubscriptionService $subscriptionService,
        private readonly Dispatcher $eventDispatcher,
    ) {
    }

    /**
     * @param mixed[] $payload
     */
    public function findOrCreateTemplate(
        Customer $customer,
        array $payload,
    ): DnsCustomerTemplate {
        $name = Arr::get($payload, 'name') ?? $this->generateTemplateName($customer);
        $records = Arr::get($payload, 'records', []);
        assert(is_string($name));
        assert(is_array($records));

        $template = DnsCustomerTemplate::where([
            'customer_id' => $customer->id,
            'name' => $name,
        ])->first();

        return $template ?? $this->createTemplate($customer, $name, $records);
    }

    /**
     * @param mixed[] $payload
     */
    public function updateTemplate(DnsCustomerTemplate $template, array $payload): DnsCustomerTemplate
    {
        $name = Arr::get($payload, 'name', false);
        $records = Arr::get($payload, 'records', []);
        assert(is_array($records));

        if ($name !== false) {
            assert(is_string($name));
            $template->update(['name' => $name]);
        }

        $template->records()->delete();
        $template->records()->createMany($records);

        $subscriptions = $template->domainDeployments;

        $subscriptions->each(function (DomainDeployment $deployment) use ($template): void {
            $domain = $deployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $event = new ZoneOutdated($template, $domain);
            $this->eventDispatcher->dispatch($event);
        });

        return $template;
    }

    public function applyTemplateToZone(DnsCustomerTemplate $template, string $zone, ?DnsZone $pdnsZone = null): void
    {
        $this->dnsService->applyTemplate($pdnsZone, $zone, $template);

        $subscription = $this->getDomainSubscription($zone);
        $template->domainDeployments()->save($subscription);
    }

    public function removeTemplateFromZone(string $zone): void
    {
        $subscription = $this->getDomainSubscription($zone);
        $subscription->template_id = null;
        $subscription->save();
    }

    /**
     * @return Collection<int, array{domain: string, available: bool, linked: bool}>
     */
    public function getSubscriptions(DnsCustomerTemplate $template): Collection
    {
        /** @var Collection<int, Subscription> $collection */
        $collection = $this->subscriptionService
            ->getSubscriptionsQuery()
            ->whereHas('domainDeployment')
            ->whereHas('product.productGroup', fn (Builder $query): Builder => $query->whereIn(
                'uuid',
                ProductGroup::whereIn('slug', [ProductGroupType::EXTENSION])->pluck('uuid'),
            ))
            ->whereNotIn('administrative_status', [AdministrativeStatus::ARCHIVED->value])
            ->orderBy('domain')
            ->with('domainDeployment')
            ->get();

        $function = function (Subscription $subscription, int $key) use ($template): array {
            Assert::notNull($subscription->domain, 'Provided subscription has no domain');

            return [
                'domain' => $subscription->domain,
                'available' => $this->checkDomainTemplateAvailability($subscription),
                'linked' => $subscription->domainDeployment?->template_id === $template->id,
            ];
        };

        return $collection->map($function)->sortBy('domain');
    }

    /**
     * @param array<mixed> $domains
     *
     * @throws DnsZoneNotFoundException
     *
     * @return Collection<int,array<string, string|DnsZone>>
     */
    public function getPdnsZonesForDomains(array $domains): Collection
    {
        $parsed = new Collection();

        foreach ($domains as $domain) {
            assert(is_array($domain));
            try {
                $stringedDomain = Arr::get($domain, 'domain', '');
                assert(is_string($stringedDomain));

                $parsed->add([
                    'domain' => $stringedDomain,
                    'zone' => $this->getPdnsZone($stringedDomain),
                ]);
            } catch (DnsZoneNotFoundException $exception) {
                $domain = Arr::get($domain, 'domain', '');
                assert(is_string($domain));

                $exception->zone = $domain;
                throw $exception;
            }
        }

        return $parsed;
    }

    private function getPdnsZone(string $zone): DnsZone
    {
        return $this->dnsService->getDnsZone($zone);
    }

    private function getDomainSubscription(string $domain): DomainDeployment
    {
        $domainDeployment = Subscription::query()
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->where('domain', $domain)
            ->firstOrFail()
            ->domainDeployment;
        assert($domainDeployment !== null);

        return $domainDeployment;
    }

    /**
     * @param mixed[] $records
     */
    private function createTemplate(
        Customer $customer,
        string $name,
        array $records,
    ): DnsCustomerTemplate {
        $template = DnsCustomerTemplate::create([
            'name' => $name,
            'customer_id' => $customer->id,
        ]);

        $template->records()->createMany($records);

        return $template->refresh();
    }

    private function generateTemplateName(Customer $customer): string
    {
        return sprintf(
            '%s-%s',
            $customer->name,
            Str::random(10),
        );
    }

    private function checkDomainTemplateAvailability(Subscription $subscription): bool
    {
        $dnsSubscriptionWithSameDomainExists = Subscription::query()
            ->whereProductGroupType(ProductGroupType::DNS)
            ->where('domain', $subscription->domain)
            ->whereNot('administrative_status', AdministrativeStatus::ARCHIVED->value)
            ->exists();

        Assert::notNull($subscription->domainDeployment, 'Subscription requires domainDeployment');

        return $dnsSubscriptionWithSameDomainExists && $subscription->domainDeployment->template_id === null;
    }
}
