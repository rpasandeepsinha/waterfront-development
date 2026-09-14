<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\Traits\RequestDomainTrait;

class DomainRetrieveRequest extends BaseRequest
{
    use RequestDomainTrait;

    private string $endpoint = 'retrieveDomainRequest';

    /**
     * @throws Exception
     */
    public function __construct(Client $client, OpenProviderConnectionInterface $connection, string $domain)
    {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message[$this->endpoint] = [
            'domain' => [
                'name' => $this->domain->getName(),
                'extension' => $this->domain->getExtension(),
            ],
        ];

        return $message;
    }
}
