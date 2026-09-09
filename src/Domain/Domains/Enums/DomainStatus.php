<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Enums;

enum DomainStatus: string
{
    /** The domain name is active. */
    case ACTIVE = 'ACT';

    /** The domain name request has failed. */
    case FAILED = 'FAI';

    /** The domain name request has been placed, but not yet finished. */
    case REQUESTED = 'REQ';

    /** The domain name has been deleted, but may still be restored. */
    case DELETED = 'DEL';

    /** The domain name request is pending further information before the process can continue. */
    case PENDING = 'PEN';

    /** A migration is in progress. LOCAL STATE DOES NOT REFER TO A PROVIDER STATE */
    case MIGRATION_PENDING = 'MIGRATING';

    /** The domain name restore has been requested, but not yet completed. */
    case RESTORE_REQUESTED = 'RRQ';

    /** The domain name is scheduled for transfer in the future. */
    case SCHEDULED = 'SCH';
}
