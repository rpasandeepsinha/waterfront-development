<?php

declare(strict_types=1);

namespace Tests\Infra\SaloonClient\Serializer;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Saloon\Exceptions\SaloonException;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Waterfront\Infra\SaloonClient\Serializer\AccessTokenAuthenticatorSerializer;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(AccessTokenAuthenticatorSerializer::class)]
class AccessTokenAuthenticatorSerializerTest extends TestCase
{
    #[Test]
    public function serializeWithExpiresAt(): void
    {
        $expiresAt = new DateTimeImmutable('2025-06-15T12:30:00+00:00');
        $authenticator = new AccessTokenAuthenticator(
            accessToken: 'my-access-token',
            refreshToken: 'my-refresh-token',
            expiresAt: $expiresAt,
        );

        $result = AccessTokenAuthenticatorSerializer::serialize($authenticator);
        $decoded = json_decode($result, true);

        self::assertIsArray($decoded);
        self::assertSame('my-access-token', $decoded['accessToken']);
        self::assertSame('my-refresh-token', $decoded['refreshToken']);
        self::assertNotNull($decoded['expiresAt']);
        self::assertIsString($decoded['expiresAt']);
    }

    #[Test]
    public function serializeWithNullExpiresAt(): void
    {
        $authenticator = new AccessTokenAuthenticator(
            accessToken: 'my-access-token',
            refreshToken: 'my-refresh-token',
        );

        $result = AccessTokenAuthenticatorSerializer::serialize($authenticator);
        $decoded = json_decode($result, true);

        self::assertIsArray($decoded);
        self::assertSame('my-access-token', $decoded['accessToken']);
        self::assertSame('my-refresh-token', $decoded['refreshToken']);
        self::assertNull($decoded['expiresAt']);
    }

    #[Test]
    public function serializeWithNullRefreshToken(): void
    {
        $authenticator = new AccessTokenAuthenticator(
            accessToken: 'my-access-token',
        );

        $result = AccessTokenAuthenticatorSerializer::serialize($authenticator);
        $decoded = json_decode($result, true);

        self::assertIsArray($decoded);
        self::assertSame('my-access-token', $decoded['accessToken']);
        self::assertNull($decoded['refreshToken']);
        self::assertNull($decoded['expiresAt']);
    }

    #[Test]
    public function unserializeWithExpiresAt(): void
    {
        $expiresAt = new DateTimeImmutable('2025-06-15T12:30:00+00:00');
        $json = json_encode([
            'accessToken' => 'my-access-token',
            'refreshToken' => 'my-refresh-token',
            'expiresAt' => $expiresAt->format(DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $result = AccessTokenAuthenticatorSerializer::unserialize($json);

        self::assertSame('my-access-token', $result->getAccessToken());
        self::assertSame('my-refresh-token', $result->refreshToken);
        self::assertNotNull($result->expiresAt);
        self::assertSame(
            $expiresAt->format(DateTimeInterface::ATOM),
            $result->expiresAt->format(DateTimeInterface::ATOM),
        );
    }

    #[Test]
    public function unserializeWithNullExpiresAt(): void
    {
        $json = json_encode([
            'accessToken' => 'my-access-token',
            'refreshToken' => 'my-refresh-token',
            'expiresAt' => null,
        ], JSON_THROW_ON_ERROR);

        $result = AccessTokenAuthenticatorSerializer::unserialize($json);

        self::assertSame('my-access-token', $result->getAccessToken());
        self::assertSame('my-refresh-token', $result->refreshToken);
        self::assertNull($result->expiresAt);
    }

    #[Test]
    public function unserializeWithNullRefreshToken(): void
    {
        $json = json_encode([
            'accessToken' => 'my-access-token',
            'refreshToken' => null,
            'expiresAt' => null,
        ], JSON_THROW_ON_ERROR);

        $result = AccessTokenAuthenticatorSerializer::unserialize($json);

        self::assertSame('my-access-token', $result->getAccessToken());
        self::assertNull($result->refreshToken);
        self::assertNull($result->expiresAt);
    }

    #[Test]
    public function roundTrip(): void
    {
        $expiresAt = new DateTimeImmutable('2025-06-15T12:30:00+00:00');
        $original = new AccessTokenAuthenticator(
            accessToken: 'round-trip-token',
            refreshToken: 'round-trip-refresh',
            expiresAt: $expiresAt,
        );

        $serialized = AccessTokenAuthenticatorSerializer::serialize($original);
        $deserialized = AccessTokenAuthenticatorSerializer::unserialize($serialized);

        self::assertSame($original->getAccessToken(), $deserialized->getAccessToken());
        self::assertSame($original->refreshToken, $deserialized->refreshToken);
        self::assertNotNull($original->expiresAt);
        self::assertNotNull($deserialized->expiresAt);
        self::assertSame(
            $original->expiresAt->format(DateTimeInterface::ATOM),
            $deserialized->expiresAt->format(DateTimeInterface::ATOM),
        );
    }

    #[Test]
    public function roundTripWithNulls(): void
    {
        $original = new AccessTokenAuthenticator(
            accessToken: 'round-trip-token',
        );

        $serialized = AccessTokenAuthenticatorSerializer::serialize($original);
        $deserialized = AccessTokenAuthenticatorSerializer::unserialize($serialized);

        self::assertSame($original->getAccessToken(), $deserialized->getAccessToken());
        self::assertNull($deserialized->refreshToken);
        self::assertNull($deserialized->expiresAt);
    }

    #[Test]
    public function unserializeThrowsOnInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessTokenAuthenticatorSerializer::unserialize('not-valid-json');
    }

    #[Test]
    public function unserializeThrowsOnNonArrayJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessTokenAuthenticatorSerializer::unserialize('"just a string"');
    }

    #[Test]
    public function unserializeThrowsOnMissingAccessTokenKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'refreshToken' => 'my-refresh-token',
            'expiresAt' => null,
        ], JSON_THROW_ON_ERROR);

        AccessTokenAuthenticatorSerializer::unserialize($json);
    }

    #[Test]
    public function unserializeThrowsOnMissingRefreshTokenKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'accessToken' => 'my-access-token',
            'expiresAt' => null,
        ], JSON_THROW_ON_ERROR);

        AccessTokenAuthenticatorSerializer::unserialize($json);
    }

    #[Test]
    public function unserializeThrowsOnMissingExpiresAtKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'accessToken' => 'my-access-token',
            'refreshToken' => 'my-refresh-token',
        ], JSON_THROW_ON_ERROR);

        AccessTokenAuthenticatorSerializer::unserialize($json);
    }

    #[Test]
    public function unserializeThrowsOnEmptyAccessToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'accessToken' => '',
            'refreshToken' => 'my-refresh-token',
            'expiresAt' => null,
        ], JSON_THROW_ON_ERROR);

        AccessTokenAuthenticatorSerializer::unserialize($json);
    }

    #[Test]
    public function unserializeThrowsOnEmptyExpiresAt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $json = json_encode([
            'accessToken' => 'my-access-token',
            'refreshToken' => 'my-refresh-token',
            'expiresAt' => '',
        ], JSON_THROW_ON_ERROR);

        AccessTokenAuthenticatorSerializer::unserialize($json);
    }

    #[Test]
    public function unserializeThrowsOnInvalidDateFormat(): void
    {
        $this->expectException(SaloonException::class);
        $this->expectExceptionMessageIsOrContains('Could not deserialize access token, invalid date');

        $json = json_encode([
            'accessToken' => 'my-access-token',
            'refreshToken' => 'my-refresh-token',
            'expiresAt' => 'not-a-valid-date',
        ], JSON_THROW_ON_ERROR);

        AccessTokenAuthenticatorSerializer::unserialize($json);
    }
}
