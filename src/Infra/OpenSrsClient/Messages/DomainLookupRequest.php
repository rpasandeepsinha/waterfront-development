<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Traits\RequestDomainTrait;

class DomainLookupRequest extends BaseRequest
{
    use RequestDomainTrait;

    /**
     * @throws Exception
     */
    public function __construct(Client $client, OpenSrsConnectionInterface $connection, string $domain)
    {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    protected function getObject(): string
    {
        return 'DOMAIN';
    }

    protected function getAction(): string
    {
        return 'LOOKUP';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAttributes(): array
    {
        return [
            'domain'   => (string) $this->domain,
            'no_cache' => '1',
        ];
    }
}
