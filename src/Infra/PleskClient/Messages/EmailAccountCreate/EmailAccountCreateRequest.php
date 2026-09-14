<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailAccountCreate;

use Illuminate\Support\Str;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

/**
 * @see https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-mail/creating-mail-accounts.34499/
 */
class EmailAccountCreateRequest implements RequestInterface
{
    public function __construct(
        public private(set) int $siteId,
        public private(set) string $emailAccount {
            set => Str::of($value)->before('@')->toString();
        },
        private readonly string $password,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'mail' => [
                'create' => [
                    'filter' => [
                        'site-id' => $this->siteId,
                        'mailname' => [
                            'name' => $this->emailAccount,
                            'mailbox' => [
                                'enabled' => 'true',
                            ],
                            'password' => [
                                'value' => $this->password,
                                'type' => 'plain',
                            ],
                            'antivir' => 'inout',
                        ],
                    ],
                ],
            ],
        ];
    }
}
