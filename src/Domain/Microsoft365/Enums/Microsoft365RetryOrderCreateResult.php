<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Enums;

enum Microsoft365RetryOrderCreateResult: string
{
    case ORDER_SUMMARY_RETRIEVAL_FAILED = 'order_summary_retrieval_failed';
    case TENANT_CREATED = 'tenant_created';
    case ORDER_CREATION_FAILED = 'order_creation_failed';
    case MCA_NOT_SIGNED = 'mca_not_signed';
    case NO_SEATS = 'no_seats';
    case ORDER_CREATED = 'order_created';
}
