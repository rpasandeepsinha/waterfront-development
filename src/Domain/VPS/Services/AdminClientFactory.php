<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use GuzzleHttp\ClientInterface;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Webmozart\Assert\Assert;

class AdminClientFactory implements AdminClientFactoryInterface
{
    public function __construct(
        private readonly ClientInterface $guzzleClient,
    ) {
    }

    /**
     * @throws AdminClientFactoryException
     */
    public function create(Environment $environment): CloudStackClient
    {
        Assert::string($environment->api_key);
        Assert::string($environment->secret_key);

        return new CloudStackClient(
            new CloudStackBaseClient(
                $environment->api_url,
                $environment->api_key,
                $environment->secret_key,
                $this->guzzleClient,
            ),
            CloudstackSerializerFactory::get(),
        );
    }
}
