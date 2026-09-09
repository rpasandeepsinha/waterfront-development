<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaValidateDomainSubscriptionAction extends NovaSubscriptionAction
{
    /** @var string[] */
    public array $validationResult = [];

    public $withoutConfirmation = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly DomainService $domainService,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DnsService $dnsService,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly GandiClient $gandiClient,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => (
                $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::EXTENSION)
            )
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.validate-dns-domain');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var Subscription $subscription */
        $subscription = $models->firstOrFail();

        $this->validateSubscription($subscription);

        return self::modal('modal-response', [
            'title' => $this->name(),
            'html' => $this->renderValidationListAsHtml(),
            'size' => '4xl',
        ]);
    }

    private function renderValidationListAsHtml(): string
    {
        $html = '<ul>';
        foreach ($this->validationResult as $item) {
            $html .= sprintf('<li>%s</li>', $item);
        }
        return $html . '</ul>';
    }

    private function validateSubscription(Subscription $subscription): void
    {
        $domain = $subscription->domain;

        if ($domain === null) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-domain'), true);
            return;
        }

        $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.has-domain', ['domain' => $domain]));

        if ($subscription->product->productGroup->slug !== ProductGroupType::EXTENSION) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-product-group-extension', ['product_group' => $subscription->product->productGroup->slug->name]), true);
            return;
        }
        $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.is-product-group-extension'));

        $domainDeployment = $subscription->domainDeployment;

        if (! $domainDeployment instanceof DomainDeployment) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-domain-deployment'), true);
            return;
        }

        $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.has-domain-deployment'));

        $providerSlug = $domainDeployment->provider->slug;

        try {
            $domainDetails = $this->domainService->fetchDomain($domain, $providerSlug);
        } catch (FetchDomainException $exception) {
            $this->addInvalidValidationResult(
                $this->translator->translate(
                    'nova-action.validate-dns-domain.domain-could-not-be-retrieved',
                    [
                        'domain' => $domain,
                        'registry' => $providerSlug->value,
                        'error' => $exception->getMessage(),
                    ]
                ),
                true
            );
            return;
        }

        $this->addValidationResult(
            $this->translator->translate(
                'nova-action.validate-dns-domain.domain-could-be-retrieved',
                [
                    'domain' => $domain,
                    'registry' => $providerSlug->value,
                ]
            )
        );

        try {
            $dnsSubscription = $this->subscriptionRepository->getSubscriptionByCustomerDomainAndType($subscription->customer, $domain, ProductGroupType::DNS);
            if ($dnsSubscription === null || ! $dnsSubscription->product->isDnsProduct()) {
                throw new ModelNotFoundException();
            }
        } catch (ModelNotFoundException) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-dns-subscription'), true);
            return;
        }

        $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.has-domain', ['product_name' => $dnsSubscription->product->name]));

        $dnsDeployment = $dnsSubscription->dnsDeployment;

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        $validWfNameservers = true;
        try {
            $wfNameservers = $this->dnsDeploymentRepository->getNameservers($dnsDeployment);
        } catch (FailedToFetchNameserversException $e) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.dns-deployment-no-nameserver', ['error' => $e->getMessage()]));
            $validWfNameservers = false;
        }

        if ($validWfNameservers) {
            $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.dns-subscription-nameservers', ['nameservers' => $this->nameserversToString($wfNameservers)]));
        }

        $validRegistryNameservers = count($domainDetails->ns) !== 0;

        if (! $validRegistryNameservers) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-domain-nameservers-registry'));
        }
        $registryNameservers = array_map(fn (string $nameserver) => new Nameserver($nameserver), $domainDetails->ns);
        $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.domain-nameservers-registry', ['nameservers' => $this->nameserversToString($registryNameservers)]));

        try {
            $dnsNameserverResponse = $this->dnsService->getDnsZone($domain)->getRecordsOfType('NS');
        } catch (DnsZoneNotFoundException $e) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-dns-zone', ['domain' => $domain, 'error' => $e->getMessage()]), true);
            return;
        }

        $validDnsNameservers = count($dnsNameserverResponse) !== 0;

        if ($this->dnsProductSpecRepository->isPremiumDns($dnsSubscription->product)) {
            try {
                $gandiDnsZone = $this->gandiClient->getDnsRecords($domain);
            } catch (NotFoundException $e) {
                $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.gandi-no-dns-zone', ['domain' => $domain, 'error' => $e->getMessage()]), true);
                return;
            }

            $validGandiDnsZone = count($gandiDnsZone) !== 0;

            if ($validGandiDnsZone) {
                $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.gandi-dns-zone-retrieved'));
            }
        }

        if (! $validDnsNameservers) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-domain-nameservers-dns-server'));
        } else {
            $dnsNameservers = array_map(fn (DnsRecordInterface $record) => new Nameserver(rtrim($record->getContent(), '.')), $dnsNameserverResponse);
            $this->addValidationResult($this->translator->translate('nova-action.validate-dns-domain.domain-nameservers-dns-server', ['nameservers' => $this->nameserversToString($dnsNameservers)]));
        }

        if (! $validWfNameservers || ! $validRegistryNameservers || ! $validDnsNameservers) {
            $this->addInvalidValidationResult($this->translator->translate('nova-action.validate-dns-domain.no-nameservers'), true);
            return;
        }

        $dnsNsHostnames = array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $dnsNameservers);
        $registryNsHostnames = array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $registryNameservers);
        $wfNsHostnames = array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $wfNameservers);

        foreach ($registryNsHostnames as $registryNsHostname) {
            if (! in_array($registryNsHostname, $dnsNsHostnames, true)) {
                $this->addInvalidValidationResult(
                    $this->translator->translate(
                        'nova-action.validate-dns-domain.nameserver-not-at',
                        [
                            'nameserver' => $registryNsHostname,
                            'at' => 'registry',
                            'notat' => 'DNS server',
                        ]
                    )
                );
            } else {
                $this->addValidationResult(
                    $this->translator->translate(
                        'nova-action.validate-dns-domain.nameserver-has-at',
                        [
                            'nameserver' => $registryNsHostname,
                            'at' => 'registry',
                            'at1' => 'DNS server',
                        ]
                    )
                );
            }

            if (! in_array($registryNsHostname, $wfNsHostnames, true)) {
                $this->addInvalidValidationResult(
                    $this->translator->translate(
                        'nova-action.validate-dns-domain.nameserver-not-at',
                        [
                            'nameserver' => $registryNsHostname,
                            'at' => 'registry',
                            'notat' => 'DNS child',
                        ]
                    )
                );
            } else {
                $this->addValidationResult(
                    $this->translator->translate(
                        'nova-action.validate-dns-domain.nameserver-has-at',
                        [
                            'nameserver' => $registryNsHostname,
                            'at' => 'registry',
                            'at1' => 'DNS child',
                        ]
                    )
                );
            }
        }

        foreach ($wfNsHostnames as $wfNsHostname) {
            if (! in_array($wfNsHostname, $dnsNsHostnames, true)) {
                $this->addInvalidValidationResult(
                    $this->translator->translate(
                        'nova-action.validate-dns-domain.nameserver-not-at',
                        [
                            'nameserver' => $wfNsHostname,
                            'at' => 'DNS child',
                            'notat' => 'DNS server',
                        ]
                    )
                );
            } else {
                $this->addValidationResult(
                    $this->translator->translate(
                        'nova-action.validate-dns-domain.nameserver-has-at',
                        [
                            'nameserver' => $wfNsHostname,
                            'at' => 'DNS child',
                            'at1' => 'DNS server',
                        ]
                    )
                );
            }
        }
    }

    private function addInvalidValidationResult(string $message, bool $stopsValidation = false): void
    {
        $message = sprintf('❌ %s', $message);

        if ($stopsValidation) {
            $message .= $this->translator->translate('nova-action.validate-dns-domain.further-validation');
        }

        $this->validationResult[] = $message;
    }

    private function addValidationResult(string $message): void
    {
        $message = sprintf('✅ %s', $message);
        $this->validationResult[] = $message;
    }

    /**
     * @param Nameserver[] $nameservers
     */
    private function nameserversToString(array $nameservers): string
    {
        return implode('<br/> ', array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $nameservers));
    }
}
