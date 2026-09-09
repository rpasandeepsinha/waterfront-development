<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Clients;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\Applications\ApplicationsList;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\Generic\ListApplicationsRequest;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class AcronisGenericClient
{
    public function __construct(
        private readonly AcronisConnector $connector,
    ) {
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function listApplications(): ApplicationsList
    {
        $response = $this->connector->send(new ListApplicationsRequest());

        try {
            $applications = AcronisSerializer::get()->deserialize($response->body(), ApplicationsList::class, 'json');
        } catch (ExceptionInterface $exception) {
            throw new AcronisSerializerException(ApplicationsList::class, $response->body(), $exception);
        }

        return $applications;
    }
}
