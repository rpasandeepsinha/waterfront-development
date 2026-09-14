<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneAlreadyCreatedException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\PowerDnsClient\Clients\InternalPowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsMetadata;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsZone;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsRecordChangeType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsValidationException;
use Waterfront\Infra\PowerDnsClient\Serializers\PowerDnsSerializerFactory;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsSoaSerialUpdater;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\IdnHelper;

/**
 * Client to PowerDNS API. It converts a DnsZone instance to a PowerDnsZone instance.
 *
 * API documentation: https://doc.powerdns.com/authoritative/http-api/index.html
 */
class PowerDnsClient
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly InternalPowerDnsClient $internalClient,
        private readonly PowerDnsZoneToDnsZoneConverter $converter,
        private readonly LoggerInterface $logger,
    ) {
        $this->serializer = PowerDnsSerializerFactory::getSerializer();
    }

    /**
     * Gets a dns zone from a domain name. The DNS zone should already exist.
     *
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getZone(string $domain): DnsZone
    {
        $powerDnsZone = $this->getPowerDnsZone($domain);

        $this->logDnsZoneInParts(
            'PowerDnsZone get domain: {domain.name}',
            $domain,
            $powerDnsZone,
        );

        return $this->converter->convertFromPowerDnsZone($powerDnsZone);
    }

    /**
     * Gets a dns zone from a domain name. The DNS zone should already exist.
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getKeys(string $domain): PowerDnsSecKeySet
    {
        $dnsSecKeys = $this->getPowerDnsZoneKeys($domain);

        return PowerDnsSecKeySet::fromArray($dnsSecKeys);
    }

    /**
     * @throws JsonException
     * @throws PdnsValidationException
     * @throws DnsZoneAlreadyCreatedException
     * @throws PdnsResponseException
     * @throws GuzzleException
     */
    public function createZone(DnsZone $dnsZone): DnsZone
    {
        $powerDnsZone = $this->converter->convertToPowerDnsZone($dnsZone);

        $this->logDnsZoneInParts(
            'PowerDnsZone create domain: {domain.name}',
            rtrim('' . $powerDnsZone->id, '.'),
            $powerDnsZone,
        );

        $request = $this->internalClient->createPostRequest(
            'api/v1/servers/localhost/zones',
            $powerDnsZone->toArray(),
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() === Response::HTTP_BAD_REQUEST) {
            throw new DnsZoneAlreadyCreatedException(
                sprintf(
                    'Zone for domain %s Already created! error message: %s',
                    $powerDnsZone->name,
                    $response->getBody()->getContents(),
                ),
            );
        }

        if ($response->getStatusCode() === Response::HTTP_UNPROCESSABLE_ENTITY) {
            throw new PdnsValidationException(
                sprintf(
                    'Zone for domain %s was provided with a invalid payload! error message: %s',
                    $powerDnsZone->name,
                    $response->getBody()->getContents(),
                ),
            );
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new PdnsResponseException(
                sprintf(
                    "Error patch zone for domain '%s' status code: %d error message from PDNS: %s",
                    $powerDnsZone->name,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }

        $responseArray = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($responseArray));

        $createdPdnsZone = PowerDnsZone::fromArray($responseArray);

        return $this->converter->convertFromPowerDnsZone($createdPdnsZone);
    }

    /**
     * Replaces the records in an entire DNS Zone.
     *
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function changeZone(DnsZone $dnsZone): DnsZone
    {
        $domain = $dnsZone->getFqdn()->toNative();
        $currentZone = $this->getZone($domain);

        $dnsZone->updateSoa();

        $diff = $currentZone->diff($dnsZone);

        if ($diff->getChanges() === []) {
            return $dnsZone;
        }

        $powerDnsZone = $this->converter->convertToPowerDnsZone($dnsZone, $diff);

        $this->patch($domain, $powerDnsZone);

        return $dnsZone;
    }

    /**
     * Update basic zone metadata. (not for records).
     *
     * @throws PdnsResponseException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function updateZone(DnsZone $dnsZone): DnsZone
    {
        $domain = $dnsZone->getFqdn()->toNative();

        $dnsZone->updateSoa();

        $powerDnsZone = $this->converter->convertToPowerDnsZone($dnsZone);

        $this->put($domain, $powerDnsZone);

        return $dnsZone;
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function addDnsRecord(string $domain, DnsRecordInterface $record): DnsZone
    {
        $dnsZone = $this->getZone($domain);
        $dnsZone->addRecord($record);
        $dnsZone->updateSoa();

        $this->patch($domain, $this->converter->convertToPowerDnsZone($dnsZone));

        return $dnsZone;
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function removeDnsRecord(string $domain, DnsRecordInterface $record): DnsZone
    {
        $dnsZone = $this->getZone($domain);
        $dnsZone->removeRecord($record);
        $dnsZone->updateSoa();

        $diff = new DnsZoneDiff([new RemovedDnsRecord($record)]);
        $this->patch($domain, $this->converter->convertToPowerDnsZone($dnsZone, $diff));

        return $dnsZone;
    }

    /**
     * @param DnsRecordInterface[] $records
     *
     * @throws PdnsResponseException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function changeDnsRecords(string $domain, array $records, PowerDnsRecordChangeType $changeType): void
    {
        $rrSets = [];

        foreach ($records as $record) {
            if (array_key_exists($record->getType(), $rrSets)) {
                continue;
            }

            $rrSets[$record->getType()] = [
                'name' => rtrim($record->getName(), '.') . '.',
                'type' => $record->getType(),
                'ttl' => $record->getTtl(),
                'changetype' => $changeType->value,
                'records' => [],
                // Records are added below
            ];
        }

        foreach ($records as $record) {
            $rrSets[$record->getType()]['records'][] = [
                'content' => $record->getContent(),
                'disabled' => $record->isDisabled(),
            ];
        }

        // We only used the array key to store the record type temporarily, so we don't need it.
        $rrSetsPayload = array_values($rrSets);

        $request = $this->internalClient
            ->createPutRequest(
                'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
                ['rrsets' => $rrSetsPayload],
            )
            ->withMethod('PATCH');

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new PdnsResponseException(
                sprintf(
                    "Error updating records for domain '%s' status code: %d error message from PDNS: %s (request payload: %s)",
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                    json_encode($rrSetsPayload, JSON_THROW_ON_ERROR),
                ),
            );
        }
    }

    /**
     * @throws GuzzleException
     *
     * See https://apidoc.cldin.eu/#/default/disablepresigned
     */
    public function disablePresignedOnZone(DnsZone $zone): void
    {
        $domain = $zone->getFqdn()->withoutTrailingDot();

        $this->internalClient->client->send(
            $this->internalClient->createGetRequest('presigned/' . $this->encodeZoneDomain($domain)),
        );
    }

    /**
     * @see https://yh-jira.atlassian.net/browse/FR-377
     *
     * @throws GuzzleException
     * @throws PdnsResponseException
     * @throws JsonException
     */
    public function changeToMasterAndEmptyMasters(DnsZone $dnsZone): void
    {
        $domain = $dnsZone->getFqdn()->withoutTrailingDot();

        $payload = [
            'kind' => PowerDnsZoneKind::MASTER->value,
            'last_check' => 0,
            'masters' => [],
        ];

        $this->logger->info(
            'Updating zone kind and empty masters',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload),
            ],
        );

        $request = $this->internalClient->createPutRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
            $payload,
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() > Response::HTTP_BAD_REQUEST) {
            throw new PdnsResponseException(
                sprintf(
                    "Error put zone for domain '%s' status code: %d error message from PDNS: %s",
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * @param DnsRecordInterface[] $records
     *
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws GuzzleException
     *
     * @internal Only for use in Ferry/DNS migrations!
     *
     */
    public function cleanupRecordsForPresigning(string $domain, array $records): void
    {
        $rrsets = array_map(
            function (DnsRecordInterface $record) {
                // Never remove the SOA record set
                if ($record->getType() === 'SOA') {
                    return [
                        'name' => $record->getName() . '.',
                        'type' => 'SOA',
                        'ttl' => $record->getTtl() ?? 3600,
                        'changetype' => PowerDnsRecordChangeType::REPLACE->value,
                        'records' => [
                            [
                                'content' => PowerDnsSoaSerialUpdater::increaseSoaSerial($record->getContent()),
                                'disabled' => false,
                            ],
                        ],
                    ];
                }

                return [
                    'name' => $record->getName() . '.',
                    'type' => $record->getType(),
                    'changetype' => PowerDnsRecordChangeType::DELETE->value,
                ];
            },
            $records,
        );

        $request = $this->internalClient
            ->createPutRequest(
                'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
                ['rrsets' => $rrsets],
            )
            ->withMethod('PATCH');

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new PdnsResponseException(
                sprintf(
                    "Error patch RRset for domain '%s' status code: %d error message from PDNS: %s (request payload: %s)",
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                    json_encode($rrsets, JSON_THROW_ON_ERROR),
                ),
            );
        }
    }

    /**
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function removeZone(string $domain): void
    {
        $this->logger->info(
            'PowerDnsZone delete zone for domain: {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ],
        );

        $request = $this->internalClient->createDeleteRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() !== Response::HTTP_NO_CONTENT) {
            throw new PdnsResponseException(
                sprintf(
                    "Error delete zone for domain '%s' status code: %d error message from PDNS: %s",
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * @throws PdnsResponseException
     * @throws GuzzleException
     *
     * @return PowerDnsMetadata[]
     *
     */
    public function getMetadata(string $domain): array
    {
        $request = $this->internalClient->createGetRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain) . '/metadata',
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            throw new PdnsResponseException(
                sprintf(
                    'Metadata for domain %s not found! error message: %s',
                    $domain,
                    $response->getBody(),
                ),
            );
        }

        /** @var PowerDnsMetadata[] $metaDataList */
        $metaDataList = $this->serializer->deserialize(
            data: $response->getBody()->getContents(),
            type: PowerDnsMetadata::class . '[]',
            format: 'json',
        );

        return $metaDataList;
    }

    /**
     * @param string[] $metaData
     *
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     * @throws PdnsValidationException
     */
    public function createMetadata(string $domain, PowerDnsMetadataType $metadataType, array $metaData): void
    {
        if ($metadataType === PowerDnsMetadataType::SOA_EDIT) {
            $this->putSoaEditMetadata($domain, $this->singleMetadataValue($metadataType, $metaData));

            return;
        }

        $payload = [
            'kind' => $metadataType->value,
            'metadata' => $metaData,
        ];

        $this->logger->info(
            sprintf('Create metadata [%s] for %s', $metadataType->value, $domain),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload),
            ],
        );

        $request = $this->internalClient->createPostRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain) . '/metadata',
            $payload,
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() > Response::HTTP_BAD_REQUEST) {
            throw new PdnsResponseException(
                sprintf(
                    'Error create metadata %s for domain %s status code: %d error message from PDNS: %s',
                    $metadataType->value,
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function deleteMetadata(string $domain, PowerDnsMetadataType $metadata): void
    {
        if ($metadata === PowerDnsMetadataType::SOA_EDIT) {
            $this->putSoaEditMetadata($domain, '');

            return;
        }

        $this->logger->info(
            sprintf('PowerDnsZone delete metadata %s for domain: {domain.name}', $metadata->value),
            [
                LoggingContextKeys::META => [
                    'meta_type' => $metadata->value,
                ],
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ],
        );

        $request = $this->internalClient->createDeleteRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain) . '/metadata/' . $metadata->value,
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() > Response::HTTP_BAD_REQUEST) {
            throw new PdnsResponseException(
                sprintf(
                    'Error delete metadata %s for domain %s status code: %d error message from PDNS: %s',
                    $metadata->value,
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function sendNotify(string $domain): void
    {
        $this->logger->info(
            'Send notify for domain: {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ],
        );

        $request = $this->internalClient->createPutRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain) . '/notify',
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() > Response::HTTP_BAD_REQUEST) {
            throw new PdnsResponseException(
                sprintf(
                    'Error send notify for domain %s status code: %d error message from PDNS: %s',
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }

        $this->sendNotifyBypass($domain);
    }

    /**
     * This API was introduced with SWD-8387 and is a custom build
     * endpoint created by CLDIN to bypass the PowerDNS queue's
     * for sending notify requests to Gandi for Premium DNS.
     */
    public function sendNotifyBypass(string $domain): void
    {
        $this->logger->debug(
            'Send custom CLDIN Gandi notify for domain: {domain.name}',
            [
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ],
        );

        $request = $this->internalClient->createGetRequest(
            'notify-gandi/' . $this->encodeZoneDomain($domain),
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->logger->error(
                sprintf(
                    'Error send notify for domain {domain.name} status code: %d no error message from CDLIN available.',
                    $response->getStatusCode(),
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ],
            );

            return;
        }

        $this->logger->debug(
            sprintf('Custom CLDIN Gandi notify response for {domain.name} is %d', $response->getStatusCode()),
            [
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ],
        );
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function updateLiveDns(string $domain, bool $enable): void
    {
        $payload = [
            'kind' => PowerDnsZoneKind::MASTER->value,
            'last_check' => 0,
            'masters' => [],
            'account' => $enable ? 'LiveDns' : '',
        ];

        $this->logger->info(
            sprintf('%s LiveDns for domain {domain.name}', $enable ? 'Enable' : 'Disable'),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload),
            ],
        );

        $request = $this->internalClient->createPutRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
            $payload,
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() > Response::HTTP_BAD_REQUEST) {
            throw new PdnsResponseException(
                sprintf(
                    'Set or remove LiveDns for domain %s with \'%s\' status code: %d error message from PDNS: %s',
                    $domain,
                    $enable ? 'LiveDns' : '',
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     *  PowerDNS 5.0 made `SOA-EDIT` read-only on the metadata endpoint, creating it is done
     *  through a PUT, and it is cleared by writing an empty `soa_edit` property on the
     *  zone object instead. An empty value is equivalent to an absent one.
     *
     * @see https://github.com/PowerDNS/pdns/issues/17899
     *
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    private function putSoaEditMetadata(string $domain, string $soaEdit): void
    {
        $payload = ['soa_edit' => $soaEdit];

        $this->logger->info(
            sprintf(
                '%s metadata %s for domain: {domain.name}',
                $soaEdit === '' ? 'Clear' : 'Set',
                PowerDnsMetadataType::SOA_EDIT->value,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload),
            ],
        );

        $request = $this->internalClient->createPutRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
            $payload,
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() > Response::HTTP_BAD_REQUEST) {
            throw new PdnsResponseException(
                sprintf(
                    'Error %s metadata %s for domain %s status code: %d error message from PDNS: %s',
                    $soaEdit === '' ? 'delete' : 'create',
                    PowerDnsMetadataType::SOA_EDIT->value,
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * @param string[] $metaData
     *
     * @throws PdnsValidationException
     */
    private function singleMetadataValue(PowerDnsMetadataType $metadataType, array $metaData): string
    {
        if (count($metaData) !== 1) {
            throw new PdnsValidationException(
                sprintf(
                    'Metadata %s takes exactly one value, %d given',
                    $metadataType->value,
                    count($metaData),
                ),
            );
        }

        return reset($metaData);
    }

    /**
     * @throws GuzzleException
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     */
    private function getPowerDnsZone(string $domain): PowerDnsZone
    {
        $request = $this->internalClient->createGetRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
        );

        $response = $this->internalClient->client->send($request);
        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            throw new DnsZoneNotFoundException(
                sprintf(
                    'Zone for domain %s not found! error message: %s',
                    $domain,
                    $response->getBody(),
                ),
            );
        }

        if ($response->getStatusCode() === 500) {
            throw new PdnsResponseException($response->getBody()->getContents());
        }

        $responseArray = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($responseArray));

        return PowerDnsZone::fromArray($responseArray);
    }

    /**
     *
     * @throws JsonException
     * @throws GuzzleException
     *
     * @return array<mixed,mixed>
     */
    private function getPowerDnsZoneKeys(string $domain): array
    {
        $request = $this->internalClient->createGetRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain) . '/cryptokeys',
        );

        $response = $this->internalClient->client->send($request);

        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        return $decoded;
    }

    /**
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function patch(string $domain, PowerDnsZone $powerDnsZone): void
    {
        $this->logDnsZoneInParts(
            'PowerDnsZone patch domain: {domain.name}',
            $domain,
            $powerDnsZone,
        );

        $request = $this->internalClient
            ->createPutRequest(
                'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
                $powerDnsZone->toArray(),
            )
            ->withMethod('PATCH');

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new PdnsResponseException(
                sprintf(
                    "Error patch zone for domain '%s' status code: %d error message from PDNS: %s",
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * @note Updating entire zone does not currently work, see
     *
     * @see https://yh-jira.atlassian.net/browse/WATER-3818
     *
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    private function put(string $domain, PowerDnsZone $powerDnsZone): void
    {
        $this->logDnsZoneInParts(
            'PowerDnsZone put domain: {domain.name}',
            $domain,
            $powerDnsZone,
        );

        $request = $this->internalClient->createPutRequest(
            'api/v1/servers/localhost/zones/' . $this->encodeZoneDomain($domain),
            $powerDnsZone->toArray(),
        );

        $response = $this->internalClient->client->send($request);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new PdnsResponseException(
                sprintf(
                    "Error put zone for domain '%s' status code: %d error message from PDNS: %s",
                    $domain,
                    $response->getStatusCode(),
                    $response->getBody()->getContents(),
                ),
            );
        }
    }

    /**
     * Create a separate log for every record in order to have no issues when patching DNS zone with a lot of records.
     *
     * @throws JsonException
     */
    private function logDnsZoneInParts(string $message, string $domain, PowerDnsZone $powerDnsZone): void
    {
        $zoneData = $powerDnsZone->toArray();
        foreach ($zoneData['rrsets'] as $rrset) {
            foreach ($rrset['records'] as $record) {
                $rrSetLogData = $rrset;
                $rrSetLogData['records'] = [$record];

                $zoneLogData = $zoneData;
                $zoneLogData['rrsets'] = [$rrSetLogData];

                $this->logger->info(
                    $message,
                    [
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::POWERDNS,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                        LoggingContextKeys::DOMAIN_NAME => rtrim($domain, '.'),
                        LoggingContextKeys::META => [
                            'powerdnszone.content' => json_encode($zoneLogData, JSON_THROW_ON_ERROR),
                        ],
                    ],
                );
            }
        }
    }

    private function encodeZoneDomain(string $domain): string
    {
        return urlencode(IdnHelper::toAscii($domain));
    }
}
