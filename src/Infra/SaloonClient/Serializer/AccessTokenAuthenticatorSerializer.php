<?php

declare(strict_types=1);

namespace Waterfront\Infra\SaloonClient\Serializer;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Saloon\Exceptions\SaloonException;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Webmozart\Assert\Assert;

/**
 * This Serializer was created as a hotfix for the following security
 * vulnerability in Saloon when serializing the AccessTokenAuthenticator:
 *
 * https://github.com/saloonphp/saloon/security/advisories/GHSA-rf88-776r-rcq9
 */
class AccessTokenAuthenticatorSerializer
{
    public static function serialize(AccessTokenAuthenticator $authenticator): string
    {
        $dateString = null;
        if ($authenticator->expiresAt instanceof DateTimeImmutable) {
            $dateString = CarbonImmutable::instance($authenticator->expiresAt)->toIso8601String();
        }

        return json_encode([
            'accessToken' => $authenticator->getAccessToken(),
            'refreshToken' => $authenticator->refreshToken,
            'expiresAt' =>  $dateString,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @throws SaloonException
     */
    public static function unserialize(string $authenticator): AccessTokenAuthenticator
    {
        $deserialized = json_decode($authenticator, true);

        Assert::isArray($deserialized);
        Assert::keyExists($deserialized, 'accessToken');
        Assert::keyExists($deserialized, 'refreshToken');
        Assert::keyExists($deserialized, 'expiresAt');
        Assert::stringNotEmpty($deserialized['accessToken']);

        if ($deserialized['expiresAt'] !== null) {
            Assert::stringNotEmpty($deserialized['expiresAt']);
        }

        $date = null;
        if ($deserialized['expiresAt'] !== null) {
            $date = DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                $deserialized['expiresAt'],
            );

            if ($date === false) {
                throw new SaloonException(
                    sprintf('Could not deserialize access token, invalid date: [%s]', $deserialized['expiresAt'])
                );
            }
        }

        return new AccessTokenAuthenticator(
            accessToken: $deserialized['accessToken'],
            refreshToken: $deserialized['refreshToken'],
            expiresAt: $date
        );
    }
}
