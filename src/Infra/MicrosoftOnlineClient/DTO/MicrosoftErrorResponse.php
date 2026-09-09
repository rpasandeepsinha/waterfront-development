<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\DTO;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\SerializedName;

class MicrosoftErrorResponse
{
    /**
     * @param int[] $errorCodes
     */
    public function __construct(
        public string $error,
        #[SerializedName('error_description')]
        public string $errorDescription,
        #[SerializedName('error_codes')]
        public array $errorCodes,
        public DateTimeImmutable $timestamp,
        #[SerializedName('trace_id')]
        public string $traceId,
        #[SerializedName('correlation_id')]
        public string $correlationId,
        #[SerializedName('error_uri')]
        public string $errorUri,
    ) {
    }
}
