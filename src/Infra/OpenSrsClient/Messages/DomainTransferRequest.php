<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Support\ContactSetBuilder;
use Waterfront\Infra\OpenSrsClient\Support\OwnerAccount;
use Waterfront\Infra\OpenSrsClient\Traits\RequestDomainTrait;

/**
 * `sw_register` with `reg_type=transfer`.
 *
 * @see https://domains.opensrs.guide/docs/transfer-commands-overview
 */
class DomainTransferRequest extends BaseRequest
{
    use RequestDomainTrait;

    private TransferParameters $parameters;

    /**
     * @throws Exception
     */
    public function __construct(Client $client, OpenSrsConnectionInterface $connection, string $domain)
    {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    public function setParameters(TransferParameters $parameters): void
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

        $attributes = [
            'domain'          => (string) $this->domain,
            'reg_type'        => 'transfer',
            'handle'          => 'process',
            'period'          => (string) $this->parameters->getPeriod(),
            'auto_renew'      => '1',
            'f_whois_privacy' => $this->parameters->getIsPrivateWhoisEnabled() ? '1' : '0',
            'reg_username'    => OwnerAccount::username($customerNumber),
            'reg_password'    => OwnerAccount::password($customerNumber),
            'contact_set'     => ContactSetBuilder::fromCustomerArray($customer),
        ];

        $transferSecret = $this->parameters->getTransferSecret();
        if ($transferSecret !== null && $transferSecret !== '') {
            $attributes['domain_auth_info'] = $transferSecret;
        }

        return $attributes;
    }
}
