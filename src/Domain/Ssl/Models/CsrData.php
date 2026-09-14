<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Models;

class CsrData
{
    public function __construct(
        private readonly CsrSubjectData $subjectData,
        private readonly string $publicKeyAlgorithm,
        private readonly int $encryptionStrength,
        private readonly ?string $signatureAlgorithm = null,
    ) {
    }

    public function getSubjectData(): CsrSubjectData
    {
        return $this->subjectData;
    }

    public function getPublicKeyAlgorithm(): string
    {
        return $this->publicKeyAlgorithm;
    }

    public function getEncryptionStrength(): int
    {
        return $this->encryptionStrength;
    }

    public function getSignatureAlgorithm(): ?string
    {
        return $this->signatureAlgorithm;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return array_filter(
            $this->subjectData->toArray()
            + [
                'publicKeyAlgorithm' => $this->getPublicKeyAlgorithm(),
                'encryptionStrength' => $this->getEncryptionStrength(),
                'signatureAlgorithm' => $this->getSignatureAlgorithm(),
            ],
            fn (mixed $value): bool => (bool) $value,
        );
    }
}
