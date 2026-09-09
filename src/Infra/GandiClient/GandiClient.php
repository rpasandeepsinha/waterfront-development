<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Waterfront\Infra\GandiClient\Connectors\GandiConnector;
use Waterfront\Infra\GandiClient\DTO\DnsRecord;
use Waterfront\Infra\GandiClient\Requests\DeleteDomainRecordsRequest;
use Waterfront\Infra\GandiClient\Requests\GetDomainRecordsRequest;
use Waterfront\Infra\GandiClient\Serializers\GandiSerializer;

class GandiClient
{
    public function __construct(
        private readonly GandiConnector $connector,
        private readonly GandiSerializer $gandiSerializer,
    ) {
    }

    /**
     * @throws RequestException
     * @throws FatalRequestException
     * @throws UnauthorizedException
     * @throws ForbiddenException
     *
     * @return DnsRecord[]
     */
    public function getDnsRecords(string $domain): array
    {
        $request = new GetDomainRecordsRequest($domain);

        $response = $this->connector->send($request);

        /** @var DnsRecord[] $dnsRecords */
        $dnsRecords = $this->gandiSerializer->deserialize($response->body(), DnsRecord::class . '[]', 'json');

        return $dnsRecords;
    }

    /**
     * @throws RequestException
     * @throws FatalRequestException
     * @throws UnauthorizedException
     * @throws ForbiddenException
     *
     * Todo: During the QA test it turned out that we did not have the correct rights on the deleteDomain endpoint.
     *       Depending on Gandi's answer, either change the request again or change the method naming.
     *       See: https://yh-jira.atlassian.net/browse/SWD-6108
     */
    public function deleteDomain(string $domain): void
    {
        $request = new DeleteDomainRecordsRequest($domain);
        $this->connector->send($request);
    }
}
