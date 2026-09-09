<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Services;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CustomerCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters as CustomerDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result as CustomerDeleteResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;
use Waterfront\Infra\PleskClient\Messages\CustomerCreate\Request as CustomerCreateRequest;
use Waterfront\Infra\PleskClient\Messages\CustomerCreate\Response as CustomerCreateResponse;
use Waterfront\Infra\PleskClient\Messages\CustomerDelete\Request as CustomerDeleteRequest;
use Waterfront\Infra\PleskClient\Messages\CustomerDelete\Response as CustomerDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListRequest;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResponse;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResult;
use Waterfront\Infra\PleskClient\Messages\HostingCustomerFetch\Request as CustomerFetchRequest;
use Waterfront\Infra\PleskClient\Messages\HostingCustomerFetch\Response as CustomerFetchResponse;
use Waterfront\Infra\PleskClient\PleskClient;
use Waterfront\Infra\PleskClient\Traits\PleskServerTrait;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CustomerClient extends PleskClient implements CustomerInterface
{
    use PleskServerTrait;

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function createCustomer(HostingParameters $parameters): CustomerCreateResult
    {
        $this->logger->info(self::class . '::create - Create new customer', [
            LoggingContextKeys::META => [
                'parameters' => $parameters->toArray(),
            ],
        ]);

        $request = new CustomerCreateRequest($parameters);
        $result = $this->send($request);
        $response = new CustomerCreateResponse($result);

        if ($response->getStatusCode() === 200 && $response->getStatus() === BaseResponse::STATUS_OK) {
            $this->logger->info(self::class . '::create - New plesk customer created successfully', [
                LoggingContextKeys::RESPONSE_CODE => $response->getStatusCode(),
                LoggingContextKeys::META => [
                    'status message' => $response->getStatusMessage(),
                    'status'         => $response->getStatus(),
                    'customer ID'    => $response->getCustomerId(),
                    'customer GUID'  => $response->getCustomerGuid(),
                    'error code'     => $response->getErrorCode(),
                    'error text'     => $response->getErrorText(),
                ],
            ]);
        }

        return $response->getResult();
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function deleteCustomer(CustomerDeleteParameters $parameters): CustomerDeleteResult
    {
        Assert::stringNotEmpty(
            $parameters->getCustomerLogin(),
            'Customer login not allowed to be empty string!'
        );

        $request = new CustomerDeleteRequest($parameters);
        $result = $this->send($request);
        $response = new CustomerDeleteResponse($result);

        return $response->getResult();
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function fetchCustomer(HostingParameters $parameters): Result
    {
        $request = new CustomerFetchRequest($parameters);
        $result = $this->send($request);
        $result = new CustomerFetchResponse($result);

        return $result->getResult();
    }

    public function getDomainList(string $customerLogin): CustomerGetDomainListResult
    {
        $request = new CustomerGetDomainListRequest($customerLogin);
        $response = new CustomerGetDomainListResponse($this->send($request));

        return $response->getResult();
    }
}
