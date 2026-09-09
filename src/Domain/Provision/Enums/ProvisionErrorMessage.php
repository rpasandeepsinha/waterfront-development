<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Enums;

enum ProvisionErrorMessage: string
{
    case DEPLOYMENT_NOT_FOUND = 'deployment_not_found';

    /**
     * This error message is used when a domain name is not allowed to be
     * coupled with the request type. For instance, you can not couple
     * an existing domain to an existing redirect or SSL deployment.
     */
    case DOMAIN_NAME_COUPLE_NOT_ALLOWED = 'domain_name_couple_not_allowed';

    /**
     * Used when a retry request references an origin request UUID that
     * cannot be found in the provisioning request store.
     */
    case RETRY_ORIGIN_NOT_FOUND = 'retry_origin_not_found';

    /**
     * Used when a retry request has a different request type than the
     * origin request it is retrying.
     */
    case RETRY_ORIGIN_TYPE_MISMATCH = 'retry_origin_type_mismatch';

    /**
     * Used when a retry request has a different request name than the
     * origin request it is retrying.
     */
    case RETRY_ORIGIN_NAME_MISMATCH = 'retry_origin_name_mismatch';

    /**
     * Used when a retry request points to an origin that is itself a
     * retry request, which is not allowed.
     */
    case RETRY_ORIGIN_IS_RETRY = 'retry_origin_is_retry';

    /**
     * Used when a retry request points to an origin whose result does not
     * have a failed provisioning status.
     */
    case RETRY_ORIGIN_NOT_FAILED = 'retry_origin_not_failed';
}
