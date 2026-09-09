<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\DTO;

class BaseKitSite implements SitebuilderSiteInterface
{
    public function __construct(
        public int $id,
        public string $domain,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
