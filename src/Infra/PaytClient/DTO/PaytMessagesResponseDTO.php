<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytMessagesResponseDTO
{
    /**
     * @param PaytMessageDTO[] $data
     */
    public function __construct(
        public array $data = [],
        public ?PaytPaginationResponseDTO $pagination = null,
    ) {
    }
}
