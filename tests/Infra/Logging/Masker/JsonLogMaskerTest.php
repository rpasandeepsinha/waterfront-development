<?php

declare(strict_types=1);

namespace Tests\Infra\Logging\Masker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskKeysInterface;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;

#[CoversClass(JsonLogMasker::class)]
class JsonLogMaskerTest extends TestCase
{
    #[Test]
    public function maskRequestAndResponseBody(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $masker = new JsonLogMasker($logger);

        $maskKeys = new class() implements MaskKeysInterface {
            public function getMaskKeys(): array
            {
                return ['password', 'token', 'secret', 'new-password', 'private-key'];
            }
        };

        $requestBody = json_encode([
            'password' => 'testpassword',
            'username' => 'testuser',
            'token' => 'testtoken',
            'role' => 'admin',
            'user_id' => 1337,
            'secret' => 'testsecret',
        ], JSON_THROW_ON_ERROR);

        $responseBody = json_encode([
            'new-password' => 'newpassword',
            'username' => 'testuser',
            'token' => 'newtoken',
            'private-key' => 'privatekeysecret',
        ], JSON_THROW_ON_ERROR);

        $maskedRequestBody = $masker->mask($requestBody, $maskKeys);
        self::assertSame(
            '{"password":"[Filtered]","username":"testuser","token":"[Filtered]","role":"admin","user_id":1337,"secret":"[Filtered]"}',
            $maskedRequestBody,
        );

        $maskedResponseBody = $masker->mask($responseBody, $maskKeys);
        self::assertSame(
            '{"new-password":"[Filtered]","username":"testuser","token":"[Filtered]","private-key":"[Filtered]"}',
            $maskedResponseBody,
        );
    }

    #[Test]
    public function maskEntireBodyForInvalidJson(): void
    {
        $logger = self::mock(LoggerInterface::class);
        $masker = new JsonLogMasker($logger);

        $maskKeys = new class() implements MaskKeysInterface {
            public function getMaskKeys(): array
            {
                return ['password', 'token', 'secret', 'new-password', 'private-key'];
            }
        };

        $logger
            ->shouldReceive('warning')
            ->times(2)
            ->with(
                sprintf(
                    'Could not mask log items for %s, invalid JSON body. Not logging body as precaution.',
                    $maskKeys::class,
                ),
            );

        $maskedRequestBody = $masker->mask('invalid json', $maskKeys);
        self::assertSame('', $maskedRequestBody);

        $maskedResponseBody = $masker->mask('invalid json', $maskKeys);
        self::assertSame('', $maskedResponseBody);
    }
}
