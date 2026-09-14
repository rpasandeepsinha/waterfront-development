<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailPasswordReset;

use Illuminate\Support\Str;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

/**
 * @see https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-mail/modifying-mail-account-settings.34506/#setting-new-mail-account-settings
 */
class EmailPasswordResetRequest implements RequestInterface
{
    public function __construct(
        public private(set) int $siteId,
        public private(set) string $emailAccount {
            set => Str::of($value)->before('@')->toString();
        },
        private readonly string $password,
    ) {
    }

    public function getMessage(): array
    {
        return [
            'mail' => [
                'update' => [
                    'set' => [
                        'filter' => [
                            'site-id' => $this->siteId,
                            'mailname' => [
                                'name' => $this->emailAccount,
                                'password' => [
                                    'value' => $this->password,
                                    'type' => 'plain',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
