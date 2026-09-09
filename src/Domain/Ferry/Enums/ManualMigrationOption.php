<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum ManualMigrationOption: string
{
    case NAMESERVERS_DO_NOTHING = 'nameservers_do_nothing';
    case NAMESERVERS_UPDATE_NEW = 'nameservers_update_new';

    case DNSSEC_DO_NOTHING = 'dnssec_do_nothing';
    case DNSSEC_ENABLE = 'dnssec_enable';

    case DNS_DO_NOTHING = 'dns_do_nothing';
    case DNS_DEFAULT_TEMPLATE = 'dns_default_template';
    case DNS_UPDATE_NATIVE = 'dns_update_native';
    case DNS_NEW_EMPTY = 'dns_new_emtpy';
}
