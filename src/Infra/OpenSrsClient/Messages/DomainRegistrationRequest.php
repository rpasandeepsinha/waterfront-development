<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\RegistrationParameters;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Support\ContactSetBuilder;
use Waterfront\Infra\OpenSrsClient\Support\OwnerAccount;
use Waterfront\Infra\OpenSrsClient\Traits\RequestDomainTrait;

/**
 * `sw_register` with `reg_type=new`.
 *
 * OpenSRS ties every domain to an owner account (`reg_username`/`reg_password`).
 * Those are derived from the customer here; persisting and reusing real owner
 * credentials is a follow-up (see the package plan).
 *
 * @see https://domains.opensrs.guide/docs/sw_register-domain-or-trust_service-
 */
class DomainRegistrationRequest extends BaseRequest
{
    use RequestDomainTrait;

    private RegistrationParameters $parameters;

    /**
     * @throws Exception
     */
    public function __construct(Client $client, OpenSrsConnectionInterface $connection, string $domain)
    {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    public function setParameters(RegistrationParameters $parameters): void
    {
        $this->parameters = $parameters;
    }

    protected function getObject(): string
    {
        return 'DOMAIN';
    }

    protected function getAction(): string
    {
        return 'SW_REGISTER';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAttributes(): array
    {
        $customer = $this->parameters->getCustomer();
        $customerNumber = (string) (Arr::get($customer, 'customer_number') ?? '');

        return [
            'domain'             => (string) $this->domain,
            'reg_type'           => 'new',
            'handle'             => 'process',
            'period'             => (string) $this->parameters->getPeriod(),
            'auto_renew'         => '0',
            'f_whois_privacy'    => $this->parameters->getIsPrivateWhoisEnabled() ? '1' : '0',
            'custom_nameservers' => '1',
            'custom_tech_contact' => '0',
            'reg_username'       => OwnerAccount::username($customerNumber),
            'reg_password'       => OwnerAccount::password($customerNumber),
            'contact_set'        => ContactSetBuilder::fromCustomerArray($customer),
            'nameserver_list'    => $this->nameserverList(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function nameserverList(): array
    {
        $list = [];

        foreach (array_values($this->parameters->getNameServers()) as $index => $nameserver) {
            assert($nameserver instanceof Nameserver);

            $list[] = [
                'name'      => $nameserver->hostname,
                'sortorder' => (string) ($index + 1),
            ];
        }

        return $list;
    }
}
