<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Enums;

enum BasekitDeletionOutcome: string
{
    case TO_DELETE = 'toDelete';
    case SKIPPED_NO_DEPLOYMENT = 'skippedNoDeployment';
    case SKIPPED_ALREADY_DELETED = 'skippedAlreadyDeleted';
}
