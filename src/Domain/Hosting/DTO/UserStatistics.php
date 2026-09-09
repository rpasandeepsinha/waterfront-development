<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

readonly class UserStatistics
{
    public function __construct(
        public ?int $activeDomains,
        public ?int $subdomains,
        public int $diskSpaceInMb,
        public ?int $mailDiskSpaceInMb,
        public ?int $mailBoxes,
        public ?int $mailLists,
        public ?int $mailAutoResponders,
        public ?int $redirects,
        public ?int $databases,
        public int $traffic,
    ) {
    }
}
