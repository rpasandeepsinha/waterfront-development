<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsNoSuchDomainException;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsRemoveDomainException;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsSsoException;
use Waterfront\Infra\SpamExpertsClient\Messages\AddDomain\Request as AddDomainRequest;
use Waterfront\Infra\SpamExpertsClient\Messages\AddDomain\Response as AddDomainResponse;
use Waterfront\Infra\SpamExpertsClient\Messages\Connection;
use Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain\Request as RemoveDomainRequest;
use Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain\Response as RemoveDomainResponse;
use Waterfront\Infra\SpamExpertsClient\Messages\Sso\Request as SsoRequest;
use Waterfront\Infra\SpamExpertsClient\Messages\Sso\Response as SsoResponse;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

/**
 * Api client for adding domains to spam experts for spam filtering.
 */
class SpamExpertsClient
{
    private readonly string $defaultSpamExpertsClusterHostname;

    /**
     * Initialize the service with the client info.
     */
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ConfigurationInterface $configuration,
    ) {
        $this->defaultSpamExpertsClusterHostname = $this->configuration->getAsString(
            'spamexpertsclient.connection.api_url',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function addDomain(string $domain, ?SpamExpertsCluster $spamExpertsCluster = null): void
    {
        $client = $this->spamExpertsClient($spamExpertsCluster);

        $this->logger->info('add domain for spam filtering', [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster !== null
                ? $spamExpertsCluster->hostname
                : $this->defaultSpamExpertsClusterHostname,
        ]);

        $request = new AddDomainRequest($client);
        $result = $request->send($domain);

        $response = new AddDomainResponse($result);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($response->getStatusMessage(), $response->getStatusCode());
        }

        if ($response->getStatus() === AddDomainResponse::STATUS_OK) {
            $this->logger->info('domain added successfully', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster !== null
                    ? $spamExpertsCluster->hostname
                    : $this->defaultSpamExpertsClusterHostname,
            ]);
        } else {
            $this->logger->info('domain could not be added', [
                LoggingContextKeys::RESPONSE_CODE => $response->getStatusCode(),
                LoggingContextKeys::RESPONSE_DATA => $response->getContent(),
                LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster !== null
                    ? $spamExpertsCluster->hostname
                    : $this->defaultSpamExpertsClusterHostname,
            ]);
        }
    }

    public function generateSsoToken(string $domain, ?SpamExpertsCluster $spamExpertsCluster = null): string
    {
        $client = $this->spamExpertsClient($spamExpertsCluster);

        $this->logger->info('generating SSO for SpamExperts for domain: ', [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster !== null
                ? $spamExpertsCluster->hostname
                : $this->defaultSpamExpertsClusterHostname,
        ]);

        $request = new SsoRequest($client);
        $result = $request->send($domain);

        $response = new SsoResponse($result);

        if ($response->getStatusCode() !== 200) {
            throw new SpamexpertsSsoException($response->getStatusMessage(), $response->getStatusCode());
        }

        if (
            $response->getStatus() === SsoResponse::STATUS_OK
            && ! str_contains($response->getContent(), 'No valid user specified')
        ) {
            $this->logger->info('domain sso generated successfully');
        } else {
            $this->logger->info('domain could not generate sso token', [
                LoggingContextKeys::RESPONSE_CODE => $response->getStatusCode(),
                LoggingContextKeys::RESPONSE_DATA => $response->getContent(),
                LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster !== null
                    ? $spamExpertsCluster->hostname
                    : $this->defaultSpamExpertsClusterHostname,
            ]);

            throw new SpamexpertsSsoException($response->getContent());
        }

        return $response->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function removeDomain(string $domain, ?SpamExpertsCluster $spamExpertsCluster = null): RemoveDomainResponse
    {
        $client = $this->spamExpertsClient($spamExpertsCluster);

        $this->logger->info('remove domain from spam filtering', [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster !== null
                ? $spamExpertsCluster->hostname
                : $this->defaultSpamExpertsClusterHostname,
        ]);

        $request = new RemoveDomainRequest($client);
        $httpResponse = $request->send($domain);

        $response = new RemoveDomainResponse($httpResponse);

        Assert::integerish($response->getStatusCode());
        if ((int) $response->getStatusCode() !== 200) {
            throw new SpamexpertsRemoveDomainException($response->getStatusMessage(), (int) $response->getStatusCode());
        }

        if ($response->getStatus() !== RemoveDomainResponse::STATUS_OK) {
            $errorBody = trim($response->getContent());

            throw match ($errorBody) {
                'ERROR: No such domain.' => new SpamexpertsNoSuchDomainException($errorBody),
                default => new SpamexpertsRemoveDomainException($errorBody),
            };
        }

        $this->logger->info(self::class . '::removeDomain - domain removed successfully');

        return $response;
    }

    private function spamExpertsClient(?SpamExpertsCluster $spamExpertsCluster): HttpClient
    {
        if ($spamExpertsCluster === null) {
            return $this->httpClient;
        }

        $connection = new Connection(
            apiUrl: $spamExpertsCluster->hostname,
            username: $spamExpertsCluster->username,
            password: $spamExpertsCluster->password,
        );

        $this->logger->debug('SpamExpertsClient using custom cluster config', [
            LoggingContextKeys::SERVER_HOSTNAME => $spamExpertsCluster->hostname,
        ]);

        return new HttpClient([
            'base_uri' => $connection->getApiUrl(),
            'headers' => [
                'Authorization' => 'Basic ' . $connection->getCredentials(),
            ],
            'http_errors' => false,
            'verify' => $spamExpertsCluster->ssl,
        ]);
    }
}
