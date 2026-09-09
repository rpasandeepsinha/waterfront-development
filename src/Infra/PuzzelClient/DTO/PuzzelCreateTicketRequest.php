<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

use Webmozart\Assert\Assert;

readonly class PuzzelCreateTicketRequest
{
    public const string TEAM_CS_ADMIN = 'CS Admin';
    public const string TEAM_CREDIT_MANAGEMENT = 'Credit Management';

    public function __construct(
        public string $subject,
        public string $body,
        public PuzzelTicketCustomer $customer,
        public string $team,
        public string|null $priority = null,
        public string|null $status = null,
        public string|null $user = null,
        /** @var array<int, string>|null */
        public array|null $tags = null,
        /** @var array<int, array{name: string, value: string}>|null */
        public array|null $categories = null,
    ) {
        Assert::oneOf($team, [self::TEAM_CREDIT_MANAGEMENT, self::TEAM_CS_ADMIN]);
    }
}
