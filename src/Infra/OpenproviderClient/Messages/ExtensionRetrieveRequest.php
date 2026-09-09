<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use GuzzleHttp\Client;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;

class ExtensionRetrieveRequest extends BaseRequest
{
    private string $endpoint = 'retrieveExtensionRequest';

    public function __construct(
        Client $client,
        OpenProviderConnectionInterface $connection,
        private readonly string $extension
    ) {
        parent::__construct($client, $connection);
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message[$this->endpoint] = [
            'name' => $this->extension,
        ];

        return $message;
    }
}
