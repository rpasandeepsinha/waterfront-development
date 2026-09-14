<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use GuzzleHttp\Client;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;

class RetrieveCustomerRequest extends BaseRequest
{
    public function __construct(
        Client $client,
        OpenProviderConnectionInterface $connection,
        private readonly string $handle,
    ) {
        parent::__construct($client, $connection);
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message['retrieveCustomerRequest'] = [
            'handle' => $this->handle,
        ];

        return $message;
    }
}
