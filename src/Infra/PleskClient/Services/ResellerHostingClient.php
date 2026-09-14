<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Services;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\ResellerHostingCreate\Request as ResellerHostingCreateRequest;
use Waterfront\Infra\PleskClient\Messages\ResellerHostingCreate\Response as ResellerHostingCreateResponse;
use Waterfront\Infra\PleskClient\Messages\ResellerHostingGetAllowedLimits\Request as ResellerHostingGetAllowedLimitsRequest
;
use Waterfront\Infra\PleskClient\Messages\ResellerHostingGetAllowedLimits\Response as ResellerHostingGetAllowedLimitsResponse
;
use Waterfront\Infra\PleskClient\Messages\ResellerHostingSetSpecs\Request as ResellerHostingSetSpecsRequest;
use Waterfront\Infra\PleskClient\Messages\ResellerHostingSetSpecs\Response as ResellerHostingSetSpecsResponse;
use Waterfront\Infra\PleskClient\PleskClient;
use Waterfront\Infra\PleskClient\Traits\PleskServerTrait;

class ResellerHostingClient extends PleskClient
{
    use PleskServerTrait;

    /**
     *
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function createResellerHosting(ResellerHostingParameters $parameters): ResellerHostingCreateResponse
    {
        $request = new ResellerHostingCreateRequest($parameters);
        $result = $this->send($request);
        $response = new ResellerHostingCreateResponse($result);

        if ($response->getStatus() === 'error') {
            throw PleskClientException::pleskApiException($response->getErrorCode(), $response->getErrorText());
        }

        return $response;
    }

    /**
     *
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function setResellerHostingSpecs(ResellerHostingParameters $parameters): ResellerHostingSetSpecsResponse
    {
        $request = new ResellerHostingSetSpecsRequest($parameters);
        $result = $this->send($request);
        $response = new ResellerHostingSetSpecsResponse($result);

        if ($response->getStatus() === 'error') {
            throw PleskClientException::pleskApiException($response->getErrorCode(), $response->getErrorText());
        }

        return $response;
    }

    /**
     *
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getAllowedLimitSpecs(ResellerHostingParameters $parameters): ResellerHostingGetAllowedLimitsResponse
    {
        $request = new ResellerHostingGetAllowedLimitsRequest($parameters);

        $result = $this->send($request);
        $response = new ResellerHostingGetAllowedLimitsResponse($result);

        if ($response->getStatus() === 'error') {
            throw PleskClientException::pleskApiException($response->getErrorCode(), $response->getErrorText());
        }

        return $response;
    }
}
