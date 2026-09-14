<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Clients;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Psr\Http\Message\RequestInterface;

class InternalPowerDnsClient
{
    public function __construct(
        public readonly ClientInterface $client,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws JsonException
     */
    public function createPostRequest(string $path, array $input): RequestInterface
    {
        $encoded = json_encode($input, JSON_THROW_ON_ERROR);

        return new Request(
            'POST',
            $path,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $encoded,
        );
    }

    public function createGetRequest(string $path): RequestInterface
    {
        return new Request(
            'GET',
            $path,
            ['Accept' => 'application/json'],
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws JsonException
     */
    public function createPutRequest(string $path, ?array $input = null): RequestInterface
    {
        $encoded = json_encode($input, JSON_THROW_ON_ERROR);

        return new Request(
            'PUT',
            $path,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $input === null ? $input : $encoded,
        );
    }

    public function createDeleteRequest(string $path): RequestInterface
    {
        return new Request(
            'DELETE',
            $path,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        );
    }
}
