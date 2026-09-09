<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\DTO;

interface SitebuilderSiteInterface
{
    public function getId(): int;

    public function getDomain(): string;
}
