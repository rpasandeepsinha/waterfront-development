<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytMandateCreateRequestDTO
{
    /**
     * @param array<string, array<int, string>> $fields
     * @param array<int, PaytMandateCreateDTO>  $pspMandates
     */
    public function __construct(
        public string $administrationId,
        public array $pspMandates,
        public ?array $fields = null,
    ) {
    }
}
