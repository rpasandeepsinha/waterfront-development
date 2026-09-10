<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Support;

/**
 * Derives the OpenSRS owner-account credentials (`reg_username` / `reg_password`)
 * that `sw_register` requires for a new registration.
 *
 * These are deterministic per customer so the same account is reused across a
 * customer's domains. Persisting real, rotatable owner credentials is a follow-up
 * (see the package plan); until then these satisfy the OpenSRS complexity rules
 * (10-20 chars, mixed letters and digits).
 */
final class OwnerAccount
{
    public static function username(string $customerNumber): string
    {
        $customerNumber = $customerNumber !== '' ? $customerNumber : '0';

        return 'wf' . $customerNumber;
    }

    public static function password(string $customerNumber): string
    {
        $customerNumber = $customerNumber !== '' ? $customerNumber : '0';

        // "Wf" + digits + hex gives upper- and lower-case letters plus digits.
        return 'Wf' . substr($customerNumber . sha1('waterfront-opensrs-' . $customerNumber), 0, 16);
    }
}
