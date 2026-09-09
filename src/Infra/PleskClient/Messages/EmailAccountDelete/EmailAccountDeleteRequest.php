<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailAccountDelete;

use Illuminate\Support\Str;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

/**
 * @see https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-mail/deleting-mail-accounts.34521/
 */
class EmailAccountDeleteRequest implements RequestInterface
{
    public function __construct(
        public private(set) int $siteId,
        public private(set) string $emailAccount {
            set => Str::of($value)->before('@')->toString();
        },
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'mail' => [
                'remove' => [
                    'filter' => [
                        'site-id' => $this->siteId,
                        'name' => $this->emailAccount,
                    ],
                ],
            ],
        ];
    }
}
