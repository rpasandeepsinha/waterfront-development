<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Policies\DnsCustomerTemplatePolicy;
use Waterfront\Apps\API\Waterfront\Requests\Dns\TemplateLinkDomainsRequest;
use Waterfront\Apps\API\Waterfront\Requests\Dns\TemplateRecordStoreRequest;
use Waterfront\Apps\API\Waterfront\Requests\Dns\TemplateStoreRequest;
use Waterfront\Apps\API\Waterfront\Requests\Dns\TemplateUnlinkDomainsRequest;
use Waterfront\Apps\API\Waterfront\Requests\Dns\TemplateUpdateRequest;
use Waterfront\Apps\API\Waterfront\Resources\TemplateResource;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Events\ZoneOutdated;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Services\Templates\TemplateService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class DnsTemplateController
{
    public function __construct(
        private readonly Dispatcher $eventDispatcher,
        private readonly DnsCustomerTemplatePolicy $dnsCustomerTemplatePolicy,
        private readonly TranslatorInterface $translator,
        private readonly TemplateService $templateService,
        private readonly AuthenticationManager $authenticationManager,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function index(
    ): ResourceCollection {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageDnsTemplates();

        return TemplateResource::collection(
            DnsCustomerTemplate::where('customer_id', $customer->id)
                ->with(['records', 'domainDeployments.subscription'])
                ->get()
        );
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function templateDomains(
        DnsCustomerTemplate $template,
    ): JsonResponse {
        $this->dnsCustomerTemplatePolicy->assertCanShow($template);

        $linkableSubscriptions = $this->templateService->getSubscriptions($template);

        return new JsonResponse(
            [
                'domains' => $linkableSubscriptions->values(),
            ]
        );
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function linkDomains(
        TemplateLinkDomainsRequest $request,
        DnsCustomerTemplate $template,
    ): Response {
        $this->customerPolicy->assertCanApplyTechnicalConfigurationToSubscriptions();
        $this->dnsCustomerTemplatePolicy->assertCanShow($template);

        try {
            $this->handleZonePropagation($template, $request->domains);
        } catch (DnsZoneNotFoundException $exception) {
            return new JsonResponse(
                ['message' => "Zone {$exception->zone} not found"],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            [
                'message' => $this->translator->translate('dns-template.link-domain-success'),
            ]
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function unlinkDomain(
        TemplateUnlinkDomainsRequest $request,
        DnsCustomerTemplate $template,
    ): Response {
        $this->customerPolicy->assertCanApplyTechnicalConfigurationToSubscriptions();
        $this->dnsCustomerTemplatePolicy->assertCanShow($template);

        try {
            // VERIFY THAT ALL ZONES EXISTS REMOTELY BEFORE WE START UPDATING.
            $collection = $this->templateService->getPdnsZonesForDomains($request->domains);
        } catch (DnsZoneNotFoundException $exception) {
            return new JsonResponse(
                ['message' => "Zone {$exception->zone} not found"],
                Response::HTTP_NOT_FOUND
            );
        }

        $collection->each(function (array $domain): void {
            $domain = Arr::get($domain, 'domain');
            assert(is_string($domain));

            $this->templateService->removeTemplateFromZone($domain);
        });

        return new JsonResponse(
            [
                'message' => $this->translator->translate('dns-template.unlink-domain-success'),
            ]
        );
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function show(
        DnsCustomerTemplate $template
    ): TemplateResource {
        $this->dnsCustomerTemplatePolicy->assertCanShow($template);

        return TemplateResource::make($template);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function store(TemplateStoreRequest $request): Response
    {
        $this->customerPolicy->assertCanManageDnsTemplates();
        $this->customerPolicy->assertCanApplyTechnicalConfigurationToSubscriptions();

        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $payload  = $request->only(['name', 'records']);

        $template = $this->templateService->findOrCreateTemplate($customer, $payload);

        return new JsonResponse([
            'data' => [
                'template_id' => $template->id,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function storeRecord(
        TemplateRecordStoreRequest $request,
        DnsCustomerTemplate $template
    ): Response {
        $this->customerPolicy->assertCanManageDnsTemplates();
        $this->customerPolicy->assertCanApplyTechnicalConfigurationToSubscriptions();

        $payload  = $request->only([
            'type',
            'name',
            'content',
            'ttl',
            'disabled',
            'priority',
            'weight',
            'port',
        ]);

        $this->templateService->updateTemplate($template, [
            'records' => $template->records->push($payload)->toArray(),
        ]);

        try {
            $this->handleZonePropagation($template);
        } catch (DnsZoneNotFoundException $exception) {
            return new JsonResponse(
                ['message' => "Zone {$exception->zone} not found"],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse([], Response::HTTP_CREATED);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function update(
        TemplateUpdateRequest $request,
        DnsCustomerTemplate $template
    ): Response {
        $this->dnsCustomerTemplatePolicy->assertCanUpdate($template);

        $payload  = $request->only(['name', 'records']);

        $this->templateService->updateTemplate($template, $payload);

        try {
            $this->handleZonePropagation($template);
        } catch (DnsZoneNotFoundException $exception) {
            return new JsonResponse(['message' => "Zone {$exception->zone} not found"], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['message' => $this->translator->translate('dns-template.update-success')]);
    }

    /**
     * @param array<mixed> $domains
     *
     * @throws DnsZoneNotFoundException
     */
    private function handleZonePropagation(
        DnsCustomerTemplate $template,
        array $domains = []
    ): void {
        // VERIFY THAT ALL ZONES EXISTS REMOTELY BEFORE WE START UPDATING.
        $collection = $this->templateService->getPdnsZonesForDomains(
            $domains === [] ? $template->domainDeployments->map(fn (DomainDeployment $deployment) => ['domain' => $deployment->subscription->domain ?? ''])->toArray() : $domains
        );

        $collection->each(function (array $domain) use ($template): void {
            $zone = Arr::get($domain, 'zone');
            $domain = Arr::get($domain, 'domain');
            assert(is_string($domain));
            assert($zone instanceof DnsZone);

            $this->eventDispatcher->dispatch(new ZoneOutdated($template, $domain, $zone));
        });
    }
}
