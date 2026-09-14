<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Illuminate\Contracts\Encryption\Encrypter;
use JsonException;
use Webmozart\Assert\Assert;

class PayloadDeserializer
{
    public function __construct(
        private readonly Encrypter $encrypter,
    ) {
    }

    /**
     * @throws JsonException
     *
     * @return array<string,mixed>
     */
    public function deserialize(string $payload): array
    {
        $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        Assert::isArray($payload);

        foreach ($payload as $parameter => $value) {
            Assert::string($parameter);
            if (is_string($value) && str_starts_with($value, 'encrypted:')) {
                $decryptedValue = $this->encrypter->decrypt(substr($value, 10));
                Assert::string($decryptedValue);
                $payload[$parameter] = $decryptedValue;
            }
        }

        return $payload;
    }
}
