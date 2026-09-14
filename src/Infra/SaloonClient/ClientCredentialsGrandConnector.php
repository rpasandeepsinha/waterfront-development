<?php

declare(strict_types=1);

namespace Waterfront\Infra\SaloonClient;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Saloon\Contracts\Authenticator;
use Saloon\Contracts\OAuthAuthenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\ClientCredentialsGrant;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Serializer\AccessTokenAuthenticatorSerializer;
use Webmozart\Assert\Assert;

abstract class ClientCredentialsGrandConnector extends AbstractConnector
{
    use ClientCredentialsGrant;

    private const string AUTHENTICATOR_CACHE_KEY = 'oauth-authenticator';

    /**
     * @param string[] $scopes
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker,
        private readonly Repository $cache,
        private readonly array $scopes = [],
        private readonly string $scopeSeparator = ' ',
        RetryConfig $retryConfig = new RetryConfig(),
    ) {
        parent::__construct($logger, $logMasker, $retryConfig);
    }

    /**
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        $authenticator = $this->fetchAuthenticator();
        parent::authenticate($authenticator);

        return parent::send($request, $mockClient, $handleRetry);
    }

    /**
     * We override the getAccessToken method from the ClientCredentialsGrant trait because
     * it calls `$this->send`. This would end up in a loop if we don't have a cache hit.
     * This method is a copy of the parent except it calls the parent::send directly.
     *
     * @template TRequest of Request
     *
     * @param array<string>                   $scopes
     * @param callable(TRequest): (void)|null $requestModifier
     */
    public function getAccessToken(
        array $scopes = [],
        string $scopeSeparator = ' ',
        bool $returnResponse = false,
        ?callable $requestModifier = null,
    ): OAuthAuthenticator|Response {
        $this->oauthConfig()->validate(withRedirectUrl: false);

        $request = $this->resolveAccessTokenRequest($this->oauthConfig(), $scopes, $scopeSeparator);

        $request = $this->oauthConfig()->invokeRequestModifier($request);

        if (is_callable($requestModifier)) {
            // @phpstan-ignore-next-line argument.type
            $requestModifier($request);
        }

        // Call parent::send instead of $this->send
        $response = parent::send($request);

        if ($returnResponse) {
            return $response;
        }

        $response->throw();

        return $this->createOAuthAuthenticatorFromResponse($response);
    }

    /**
     * Either get the authenticator from the cache or retrieve the
     * access token using Saloon's build in OAuth functionality
     * when the cache misses or the token has been expired.
     */
    private function fetchAuthenticator(): Authenticator
    {
        /** @var ?string $authenticator */
        $authenticator = $this->cache->get($this->getCacheKey());

        if ($authenticator !== null) {
            $authenticator = AccessTokenAuthenticatorSerializer::unserialize($authenticator);

            if ($authenticator->hasNotExpired()) {
                return $authenticator;
            }
        }

        $authenticator = self::getAccessToken($this->scopes, $this->scopeSeparator);
        Assert::isInstanceOf($authenticator, AccessTokenAuthenticator::class);

        $ttl = 1;
        if ($authenticator->getExpiresAt() !== null) {
            $expiresAt = CarbonImmutable::instance($authenticator->getExpiresAt());
            $ttl = max(1, (int) CarbonImmutable::now()->diffInSeconds($expiresAt));
        }

        $this->cache->set(
            key: $this->getCacheKey(),
            value: AccessTokenAuthenticatorSerializer::serialize($authenticator),
            ttl: $ttl,
        );

        return $authenticator;
    }

    private function getCacheKey(): string
    {
        return sprintf('%s-%s', self::class, self::AUTHENTICATOR_CACHE_KEY);
    }
}
