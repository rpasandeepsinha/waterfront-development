<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytMessageDTO
{
    public const string SENDER_TYPE_DEBTOR = 'debtor';

    public function __construct(
        public string $id,
        public string $senderType,
        public string $content,
        public string|null $sentAt,
        public string|null $receivedAt,
        public string|null $subject,
        public string|null $creditCaseId,
    ) {
    }
}
