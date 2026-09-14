<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Waterfront\Domain\Domains\DTO\HandleParameters;

class DomainHandleRequest extends BaseRequest
{
    private string $endpoint = 'createCustomerRequest';

    private HandleParameters $parameters;

    public function setParameters(HandleParameters $parameters): void
    {
        // OpenProvider expects a locale in the format of nl_NL, en_US, etc.
        $parameters->setLocale(str_replace('-', '_', $parameters->getLocale()));
        $this->parameters = $parameters;
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message[$this->endpoint] = [
            'companyName' => $this->parameters->getCompanyName(),
            'vat' => $this->parameters->getVat(),
            'name' => [
                'initials' => $this->parameters->getInitials(),
                'firstName' => $this->parameters->getFirstName(),
                'prefix' => $this->parameters->getPrefix(),
                'lastName' => $this->parameters->getLastName(),
            ],
            'phone' => [
                'countryCode' => $this->parameters->getPhoneCountryCode(),
                'areaCode' => $this->parameters->getPhoneAreaCode(),
                'subscriberNumber' => $this->parameters->getPhoneSubscriberNumber(),
            ],
            'address' => [
                'street' => $this->parameters->getAddressStreet(),
                'number' => $this->parameters->getAddressNumber(),
                'suffix' => $this->parameters->getAddressSuffix(),
                'zipcode' => $this->parameters->getAddressZipcode(),
                'city' => $this->parameters->getAddressCity(),
                'state' => $this->parameters->getAddressState(),
                'country' => $this->parameters->getAddressCountry(),
            ],
            'email' => $this->parameters->getEmail(),
            'locale' => $this->parameters->getLocale(),
        ];

        if ($this->parameters->faxIsSet()) {
            $message['fax'] = [
                'countryCode' => $this->parameters->getFaxCountryCode(),
                'areaCode' => $this->parameters->getFaxAreaCode(),
                'subscriberNumber' => $this->parameters->getFaxSubscriberNumber(),
            ];
        }

        return $message;
    }
}
