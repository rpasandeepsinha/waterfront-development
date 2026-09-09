<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use GuzzleHttp\Client;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;

class SearchDomainRequest extends BaseRequest
{
    private string $endpoint = 'searchDomainRequest';

    /**
     * @param array<string, string> $parameters
     */
    public function __construct(Client $client, OpenProviderConnectionInterface $connection, private readonly array $parameters)
    {
        parent::__construct($client, $connection);
    }

    /**
     * @inheritDoc
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message[$this->endpoint] = $this->parameters;

        return $message;
    }
}
