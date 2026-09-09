<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\Factory;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PaytClient\DTO\PaytCredentials;
use Waterfront\Infra\PaytClient\Enums\PaytSupportedBusinessUnit;
use Waterfront\Infra\PaytClient\PaytClient;

class PaytClientFactory
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly Serializer $serializer,
        private readonly ConfigurationInterface $config,
    ) {
    }

    public function create(PaytSupportedBusinessUnit $businessUnit): PaytClient
    {
        $credentials = new PaytCredentials(
            apiKey: $this->config->getAsString('paytclient.api_key'),
            administrationId: $this->config->getAsString("financial.bu-payt.{$businessUnit->value}.administration_id"),
        );

        return new PaytClient(
            httpClient: $this->httpClient,
            logger: $this->logger,
            serializer: $this->serializer,
            credentials: $credentials,
        );
    }
}
