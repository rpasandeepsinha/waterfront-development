<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Traits\RequestDomainTrait;

/**
 * `get` domain. The `type` attribute selects the projection: `all_info`,
 * `domain_auth_info`, `status`, `nameservers`, ...
 *
 * @see https://domains.opensrs.guide/docs/get-domain
 */
class DomainGetRequest extends BaseRequest
{
    use RequestDomainTrait;

    /**
     * @throws Exception
     */
    public function __construct(
        Client $client,
        OpenSrsConnectionInterface $connection,
        string $domain,
        private readonly string $type = 'all_info',
    ) {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    public function getType(): string
    {
        return $this->type;
    }

    protected function getObject(): string
    {
        return 'DOMAIN';
    }

    protected function getAction(): string
    {
        return 'GET';
    }

    /**
     * {@inheritDoc}
     */
    protected function getTopLevelFields(): array
    {
        return ['domain' => (string) $this->domain];
    }

    /**
     * {@inheritDoc}
     */
    protected function getAttributes(): array
    {
        return ['type' => $this->type];
    }
}
