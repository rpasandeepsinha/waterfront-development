<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Clients;

use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems\OfferingItemsFilter;
use Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems\OfferingItemsPricing;
use Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems\OfferingItemsPricing as OfferingItemsPricingResponse;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\GetOfferingItemsPricingRequest;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\GetOfferingItemsRequest;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\PutOfferingItemsPricingRequest;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\PutOfferingItemsRequest;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class AcronisOfferingItemsClient
{
    public function __construct(
        private readonly AcronisConnector $connector,
    ) {
    }

    /**
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisSerializerException
     */
    public function get(string $tenantId, ?OfferingItemsFilter $filter = null): OfferingItems
    {
        $filter ??= new OfferingItemsFilter();
        $response = $this->connector->send(new GetOfferingItemsRequest(
            tenantId: $tenantId,
            filter: $filter,
        ));

        try {
            $offeringItems = AcronisSerializer::get()->deserialize($response->body(), OfferingItems::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(OfferingItems::class, $response->body(), $exception);
        }

        return $offeringItems;
    }

    /**
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisSerializerException
     */
    public function update(string $tenantId, OfferingItems $payload): OfferingItems
    {
        $response = $this->connector->send(new PutOfferingItemsRequest(
            tenantId: $tenantId,
            payload: $payload,
        ));

        try {
            $offeringItems = AcronisSerializer::get()->deserialize($response->body(), OfferingItems::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(OfferingItems::class, $response->body(), $exception);
        }

        return $offeringItems;
    }

    /**
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisSerializerException
     */
    public function getPricing(string $tenantId): OfferingItemsPricingResponse
    {
        $response = $this->connector->send(new GetOfferingItemsPricingRequest(
            tenantId: $tenantId,
        ));

        try {
            $pricing = AcronisSerializer::get()->deserialize(
                $response->body(),
                OfferingItemsPricingResponse::class,
                'json',
            );
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(OfferingItemsPricingResponse::class, $response->body(), $exception);
        }

        return $pricing;
    }

    /**
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisSerializerException
     */
    public function updatePricing(string $tenantId, OfferingItemsPricing $payload): OfferingItemsPricingResponse
    {
        $response = $this->connector->send(new PutOfferingItemsPricingRequest(
            tenantId: $tenantId,
            payload: $payload,
        ));

        try {
            $pricing = AcronisSerializer::get()->deserialize(
                $response->body(),
                OfferingItemsPricingResponse::class,
                'json',
            );
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(OfferingItemsPricingResponse::class, $response->body(), $exception);
        }

        return $pricing;
    }
}
