<?php

declare(strict_types=1);

namespace Tests\Apps\API\Webhooks\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Apps\Webhooks\Services\Payt\PaytWebhookSignatureValidator;

#[CoversClass(PaytWebhookSignatureValidator::class)]
class PaytWebhookSignatureValidatorTest extends TestCase
{
    private const string SECRET = 'test-secret';
    private const string BODY = '{"event":{"event_name":"event"}}';

    // Signatures pre-generated with: hash_hmac('sha256', BODY, SECRET)
    private const string VALID_SIGNATURE = 'c3b063c9650ec47847b1891abe366e04f588e80cc69f1bfc8d8f2d4e8b330acf';
    private const string SIGNATURE_FOR_DIFFERENT_BODY = '9f01c7e79cc331800460fcb16e8fde2d0aacc0b3e42e755d77050e7b21fa3a3f';
    private const string SIGNATURE_FOR_DIFFERENT_SECRET = '1e0268c51a8f9a3cc7dd4ccb0d4a84617119d61362cbded7d7d11aed3c6b5636';

    #[DataProvider('signatureDataProvider')]
    #[Test]
    public function validate(string $signature, bool $expectedResult): void
    {
        $validator = new PaytWebhookSignatureValidator();

        self::assertSame($expectedResult, $validator->validate(self::BODY, $signature, self::SECRET));
    }

    /**
     * @return array<string, array{signature: string, expectedResult: bool}>
     */
    public static function signatureDataProvider(): array
    {
        return [
            'valid signature' => ['signature' => self::VALID_SIGNATURE, 'expectedResult' => true],
            'invalid signature' => ['signature' => 'invalid-signature', 'expectedResult' => false],
            'empty signature' => ['signature' => '', 'expectedResult' => false],
            'signature for different body' => [
                'signature' => self::SIGNATURE_FOR_DIFFERENT_BODY,
                'expectedResult' => false,
            ],
            'signature for different secret' => [
                'signature' => self::SIGNATURE_FOR_DIFFERENT_SECRET,
                'expectedResult' => false,
            ],
        ];
    }
}
