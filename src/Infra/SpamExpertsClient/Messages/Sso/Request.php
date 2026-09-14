<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Messages\Sso;

use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;

class Request
{
    private string $endpoint = 'api/authticket/create/username/{domain}';

    public function __construct(
        private readonly HttpClient $httpClient,
    ) {
    }

    public function send(string $domain): ResponseInterface
    {
        $path = str_replace('{domain}', urlencode($domain), $this->endpoint);

        return $this->httpClient->request('GET', $path);
    }
}
