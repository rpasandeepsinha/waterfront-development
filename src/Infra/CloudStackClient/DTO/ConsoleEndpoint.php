<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedPath;

class ConsoleEndpoint
{
    public function __construct(
        public bool $success,
        public ?string $details,
        public ?string $url,
        #[SerializedPath('[websocket][token]')]
        public ?string $websocketToken,
        #[SerializedPath('[websocket][host]')]
        public ?string $websocketHost,
        #[SerializedPath('[websocket][port]')]
        public ?string $websocketPort,
        #[SerializedPath('[websocket][path]')]
        public ?string $websocketPath,
    ) {
    }
}
