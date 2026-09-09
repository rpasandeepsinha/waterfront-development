<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Enums;

enum BasekitMigrationEligibility: string
{
    case ELIGIBLE = 'eligible';
    case ALREADY_MIGRATED = 'skippedAlreadyMigrated';
    case NOT_SITEBUILDER = 'skippedNotSitebuilder';
    case NO_PACKAGE_REFERENCE = 'skippedNoPackageReference';
    case MISSING_DATA = 'skippedMissingData';
}
