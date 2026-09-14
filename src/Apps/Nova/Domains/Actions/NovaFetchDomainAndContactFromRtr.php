<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaFetchDomainAndContactFromRtr extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DomainService $domainService,
        private readonly DomainProviderBusinessUnitRepository $businessUnitRepository,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_domain_and_domain-contact_from_rtr');
    }

    /**
     * @param Collection<int, DomainDeployment> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $models->first();
        $type = $domainDeployment->provider->slug;

        try {
            $remoteDomain = $this->domainService->fetchDomain($domainDeployment->subscription->domain ?? '', $type);

            /** @var DomainContact|null $contactOwner */
            $contactOwner = $domainDeployment->contactOwner;
            $localDatabaseContacts = [];

            if ($contactOwner !== null) {
                $handles = $contactOwner
                    ->providers()
                    ->withPivot(['external_contact', 'domain_business_unit_id'])
                    ->get();

                foreach ($handles as $handle) {
                    Assert::string($handle->pivot->external_contact);
                    $businessUnit = null;
                    if ($handle->pivot->domain_business_unit_id !== null) {
                        Assert::integerish($handle->pivot->domain_business_unit_id);
                        $businessUnit = $this->businessUnitRepository->findById((int) $handle->pivot->domain_business_unit_id);
                    }

                    $localDatabaseContacts[] = $this->domainService
                        ->retrieveContactHandle(
                            $handle->pivot->external_contact,
                            $type,
                            $businessUnit,
                        )
                        ->toArray();
                }
            }

            $remoteContacts = [];
            $remoteContacts[sprintf('Registrant:%s', $remoteDomain->registrant)] = $this->domainService
                ->retrieveContactHandle($remoteDomain->registrant, $type, $domainDeployment->businessUnit)
                ->toArray();

            $contactArray = $remoteDomain->contacts?->entities;

            if ($contactArray !== null) {
                foreach ($contactArray as $remoteContactDefinition) {
                    $remoteContacts[sprintf(
                        '%s:%s',
                        $remoteContactDefinition->role,
                        $remoteContactDefinition->handle,
                    )] = $this->domainService
                        ->retrieveContactHandle(
                            $remoteContactDefinition->handle,
                            $type,
                            $domainDeployment->businessUnit,
                        )
                        ->toArray();
                }
            }

            $serializer = DomainSerializerFactory::getSerializer();

            $domainArray = $serializer->normalize($remoteDomain);

            $data = [
                'domain' => $domainArray,
                'contacts_registered_in_database' => $localDatabaseContacts,
                'contacts_from_remote_domain' => $remoteContacts,
            ];
        } catch (RealtimeRegisterClientException $exception) {
            $data = [
                'exception' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return self::modal('modal-response', [
            'title' => 'Fetched domain details and handles from Rtr with response:',
            'code' => json_encode($data, JSON_PRETTY_PRINT),
        ]);
    }
}
