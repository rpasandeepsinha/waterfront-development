<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class DnssecKeyRule implements ValidationRule, DataAwareRule
{
    private const int REQUIRED_ALGORITHM = 13;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly string $flagsFieldName = 'flags',
        private readonly string $algorithmFieldName = 'alg',
        private readonly string $publicKeyFieldName = 'pubKey',
    ) {
    }

    /** @phpstan-param array<mixed> $data */
    public function setData(array $data): static
    {
        /** @var array<string, mixed> $data */
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $flagsValue = $this->data[$this->flagsFieldName] ?? null;
        $algorithmValue = $this->data[$this->algorithmFieldName] ?? null;
        $publicKeyValue = $this->data[$this->publicKeyFieldName] ?? null;

        if ($flagsValue === null && $algorithmValue === null && $publicKeyValue === null) {
            return;
        }

        $isFlagsNumeric = is_int($flagsValue) || is_string($flagsValue) && ctype_digit($flagsValue);
        $isAlgorithmNumeric = is_int($algorithmValue) || is_string($algorithmValue) && ctype_digit($algorithmValue);

        $flagsInt = $isFlagsNumeric ? (int) $flagsValue : 0;
        $algorithmInt = $isAlgorithmNumeric ? (int) $algorithmValue : 0;

        if (! in_array($flagsInt, [256, 257], true)) {
            $fail('dns.validation.dnssec.flags_invalid');

            return;
        }

        if ($algorithmInt !== self::REQUIRED_ALGORITHM) {
            $fail('dns.validation.dnssec.algorithm_invalid');

            return;
        }

        if (! is_string($publicKeyValue)) {
            $fail('dns.validation.dnssec.public_key_string');

            return;
        }

        $normalizedPublicKey = preg_replace('/\s+/', '', $publicKeyValue);
        if ($normalizedPublicKey === null || $normalizedPublicKey === '') {
            $fail('dns.validation.dnssec.public_key_base64');

            return;
        }

        $decodedPublicKey = base64_decode($normalizedPublicKey, true);
        if ($decodedPublicKey === false || base64_encode($decodedPublicKey) !== $normalizedPublicKey) {
            $fail('dns.validation.dnssec.public_key_base64');

            return;
        }
    }

    /**
     * @param array<string, int|string|null> $payload
     */
    public static function assert(array $payload): void
    {
        $validator = Validator::make(
            $payload,
            [
                'pubKey' => [
                    new self(
                        flagsFieldName: 'flags',
                        algorithmFieldName: 'alg',
                        publicKeyFieldName: 'pubKey',
                    ),
                ],
            ],
        )->stopOnFirstFailure();

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }
    }
}
