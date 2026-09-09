<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Request
{
    /** @var string */
    private $endpoint = 'api/domain/remove/domain/{domain}';

    public function __construct(private readonly HttpClient $httpClient)
    {
    }

    /**
     * @throws GuzzleException
     */
    public function send(string $domain): ResponseInterface
    {
        $path = str_replace('{domain}', urlencode($domain), $this->endpoint);

        return $this->httpClient->request('GET', $path);
    }
}
