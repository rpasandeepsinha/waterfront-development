<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Messages\AddDomain;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Request
{
    /** @var string */
    private $endpoint = 'api/domain/add/domain/{domain}/destinations/{destinations}';

    public function __construct(
        private readonly HttpClient $client,
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function send(string $domain): ResponseInterface
    {
        $path = str_replace(
            ['{domain}', '{destinations}'],
            [urlencode($domain), urlencode(json_encode(['mail.' . $domain], JSON_THROW_ON_ERROR))],
            $this->endpoint,
        );

        return $this->client->request('GET', $path);
    }
}
