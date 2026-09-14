<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication;

use Carbon\FactoryImmutable;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\MissingMandatoryClaimException;
use Jose\Component\Checker\NotBeforeChecker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\EdDSA;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class OathKeeperService
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array<mixed>|null */
    public function retrieveValidatedJwt(string $token): ?array
    {
        $jwkSet = $this->getJwkSet();

        if ($jwkSet === null) {
            return null;
        }

        $jwt = $this->getVerifiedJwt($token, $jwkSet);

        if (! is_array($jwt)) {
            return null;
        }

        return $this->checkClaims($jwt) ? $jwt : null;
    }

    private function getJwkSet(): ?JWKSet
    {
        $jwksEndpoint = $this->configuration->getAsString('auth.oathkeeper_jwks_url');

        try {
            $jwks = Http::get($jwksEndpoint)->json();
        } catch (Exception $exception) { // @phpstan-ignore-line Yes, I know Exception is too broad, but it's what Laravel throws...
            $this->logger->notice('Error in getting JWKS from Oathkeeper: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return null;
        }

        if (! is_array($jwks)) {
            return null;
        }

        try {
            $jwkSet = JWKSet::createFromKeyData($jwks);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $jwkSet;
    }

    /** @return array<mixed>|null */
    private function getVerifiedJwt(string $token, JWKSet $jwkSet): ?array
    {
        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);
        $jwsVerifier = new JWSVerifier(new AlgorithmManager([new EdDSA()]));
        $jwsLoader = new JWSLoader(
            $serializerManager,
            $jwsVerifier,
            null,
        );

        $strippedToken = Str::replaceFirst('Bearer ', '', $token);

        try {
            $jwt = $jwsLoader->loadAndVerifyWithKeySet($strippedToken, $jwkSet, $signature)->getPayload();
        } catch (Exception $exception) { // @phpstan-ignore-line Yes, I know Exception is too broad, but it's what JwsLoader throws...
            $this->logger->notice('Error in verifying keyset from Oathkeeper: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return null;
        }

        if ($jwt === null) {
            return null;
        }

        try {
            $jwt = json_decode($jwt, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) { // @phpstan-ignore-line Yes, I know Exception is too broad, but it's what json_decode throws...
            return null;
        }

        if (! is_array($jwt)) {
            return null;
        }

        return $jwt;
    }

    /**
     * @param array<mixed> $jwt
     */
    private function checkClaims(array $jwt): bool
    {
        $jwtIssuer = $this->configuration->getAsString('auth.oathkeeper_jwt_issuer');

        $clock = new FactoryImmutable();
        $claimCheckerManager = new ClaimCheckerManager([
            new IssuedAtChecker($clock),
            new NotBeforeChecker($clock),
            new ExpirationTimeChecker($clock),
            new IssuerChecker([$jwtIssuer]),
        ]);

        try {
            $claimCheckerManager->check($jwt, ['session', 'sub']);
        } catch (InvalidClaimException|MissingMandatoryClaimException $exception) {
            $this->logger->notice('Error in verifying JWT claim: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return false;
        }

        return true;
    }
}
