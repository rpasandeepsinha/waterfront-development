<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Waterfront\Domain\Domains\Enums\DomainStatus;

class RetrieveCustomerResponse
{
    public function __construct(
        private ?DomainStatus $status,
        private ?string $reason,
        private int $responseCode,
        private string $handle,
        private ?string $organization,
        private string $vat,
        private string $firstName,
        private string $lastName,
        private string $gender,
        private string $phone,
        private string $email,
        private string $streetName,
        private string $streetNumber,
        private string $zip,
        private string $city,
        private string $countryCode
    ) {
    }

    public static function fromXMLResponse(ResponseInterface $response): RetrieveCustomerResponse
    {
        $object = new RetrieveCustomerResponse(null, null, 0, '', null, '', '', '', '', '', '', '', '', '', '', '');
        $object->parseReply((string) $response->getBody());
        return $object;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function getVat(): float
    {
        return floatval($this->vat);
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStreet(): ?string
    {
        return $this->streetName;
    }

    public function getStreetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        if ($this->responseCode === 0) {
            return [
                'organization' => $this->organization,
                'first_name'   => $this->firstName,
                'last_name'    => $this->lastName,
                'gender'       => $this->gender,
                'phone'        => $this->phone,
                'email'        => $this->email,
                'address'      => [
                    'street' => $this->streetName,
                    'number' => $this->streetNumber,
                    'zipcode' => $this->zip,
                    'city' => $this->city,
                    'country' => $this->countryCode,
                ],
            ];
        }

        return [
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }

    /**
     * Get the response info for the domain registration.
     */
    private function collectRegistrationInfo(SimpleXMLElement $data): void
    {
        $name = $data->name;
        /** @var SimpleXMLElement $name */
        $this->firstName = (string) ($name->firstName ?? $name->initials);
        $this->lastName = $name->prefix . ' ' . $name->lastName;

        $this->handle = (string) $data->handle;
        $this->gender = (string) $data->gender;

        $phone = $data->phone->countryCode . $data->phone->areaCode . $data->phone->subscriberNumber;
        $this->phone = $phone;
        $this->vat = (string) $data->vat;
        $this->email = (string) $data->email;
        $organization = (string) $data->companyName;
        if ($organization !== '') {
            $this->organization = $organization;
        }

        if ($data->address !== null) {
            $address = (array) $data->address;
            $this->streetName = $address['street'];
            $this->streetNumber = $address['number'];
            $this->zip = $address['zipcode'];
            $this->city = $address['city'];
            $this->countryCode = $address['country'];
        }
    }

    /**
     * Pull relevant info from the response body.
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = (int) $xmlResponse->reply->code;
        $this->responseCode = $responseCode;
        if ($responseCode === 0) {
            $this->collectRegistrationInfo($xmlResponse->reply->data);
        } else {
            $this->status = DomainStatus::FAILED;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
