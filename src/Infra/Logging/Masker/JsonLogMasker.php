<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\Masker;

use Psr\Log\LoggerInterface;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskKeysInterface;

class JsonLogMasker implements MaskerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function mask(string $body, MaskKeysInterface $maskKeys): string
    {
        if (! json_validate($body)) {
            $this->logger->warning(
                sprintf(
                    'Could not mask log items for %s, invalid JSON body. Not logging body as precaution.',
                    $maskKeys::class
                )
            );
            return '';
        }

        /** @var mixed[] $items */
        $items = json_decode($body, true);
        $maskKeys = $maskKeys->getMaskKeys();

        foreach ($items as $key => $value) {
            if (in_array($key, $maskKeys, true)) {
                $items[$key] = '[Filtered]';
            }
        }

        return json_encode($items, JSON_THROW_ON_ERROR);
    }
}
