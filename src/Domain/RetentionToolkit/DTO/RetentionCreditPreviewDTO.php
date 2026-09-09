<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\DTO;

readonly class RetentionCreditPreviewDTO
{
    public function __construct(
        public int $creditTotal,
        public bool $replacesFutureInvoice,
    ) {
    }
}
