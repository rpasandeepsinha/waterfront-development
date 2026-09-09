<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Fakers;

use Exception;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CustomerCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters as CustomerDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result as CustomerDeleteResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\CustomerCreate\Response as CustomerCreateResponse;
use Waterfront\Infra\PleskClient\Messages\CustomerDelete\Response as CustomerDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\HostingCustomerFetch\Response as CustomerFetchResponse;
use Waterfront\Infra\PleskClient\Services\CustomerClient;
use Waterfront\Infra\PleskClient\Traits\DesiredResponseCodeTrait;
use Webmozart\Assert\Assert;

/**
 * Faker to mock the customer client.
 */
class CustomerClientFaker extends CustomerClient
{
    use DesiredResponseCodeTrait;

    /**
     * Method for sending the request for creating a Plesk customer.
     * This is placed in its own method to make it easy for the faker to override only this part.
     */
    public function createCustomer(HostingParameters $parameters): CustomerCreateResult
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_customer_create_response.xml');

        /** @var string $xmlMessage */
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new CustomerCreateResponse($httpResponse)->getResult();
    }

    /**
     * @throws Exception
     */
    public function deleteCustomer(CustomerDeleteParameters $parameters): CustomerDeleteResult
    {
        Assert::stringNotEmpty(
            $parameters->getCustomerLogin(),
            'Customer login not allowed to be empty string!'
        );

        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_customer_delete_response.xml');

        /** @var string $xmlMessage */
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new CustomerDeleteResponse($httpResponse)->getResult();
    }

    public function fetchCustomer(HostingParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_customer_fetch_response.xml');

        /** @var string $xmlMessage */
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new CustomerFetchResponse($httpResponse)->getResult();
    }
}
