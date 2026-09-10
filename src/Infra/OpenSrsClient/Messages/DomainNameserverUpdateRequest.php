<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Traits\RequestDomainTrait;

/**
 * `advanced_update_nameservers` with `op_type=assign` — replaces the delegation
 * set for a domain.
 *
 * @see https://domains.opensrs.guide/docs/dns-zone-commands-overview
 */
class DomainNameserverUpdateRequest extends BaseRequest
{
    use RequestDomainTrait;

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws Exception
     */
    public function __construct(
        Client $client,
        OpenSrsConnectionInterface $connection,
        string $domain,
        private readonly array $nameServers,
    ) {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    protected function getObject(): string
    {
        return 'DOMAIN';
    }

    protected function getAction(): string
    {
        return 'ADVANCED_UPDATE_NAMESERVERS';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAttributes(): array
    {
        return [
            'domain'    => (string) $this->domain,
            'op_type'   => 'assign',
            'assign_ns' => array_values(array_map(
                static fn (Nameserver $nameServer): string => $nameServer->hostname,
                $this->nameServers,
            )),
        ];
    }
}
