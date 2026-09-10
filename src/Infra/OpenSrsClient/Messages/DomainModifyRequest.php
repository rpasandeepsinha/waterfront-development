<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Traits\RequestDomainTrait;

/**
 * `modify` domain. The `data` attribute selects the modification; each `data`
 * value carries its own set of companion attributes, so a single logical
 * "modify" over several aspects is several of these requests.
 *
 * @see https://domains.opensrs.guide/docs/modify-domain
 */
class DomainModifyRequest extends BaseRequest
{
    use RequestDomainTrait;

    /**
     * @param array<string, string> $data companion attributes for the given modification type
     *
     * @throws Exception
     */
    public function __construct(
        Client $client,
        OpenSrsConnectionInterface $connection,
        string $domain,
        private readonly string $modificationType,
        private readonly array $data,
    ) {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    public function getModificationType(): string
    {
        return $this->modificationType;
    }

    protected function getObject(): string
    {
        return 'DOMAIN';
    }

    protected function getAction(): string
    {
        return 'MODIFY';
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
        return [
            'data'           => $this->modificationType,
            'affect_domains' => '0',
            ...$this->data,
        ];
    }
}
