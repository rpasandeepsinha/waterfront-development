<?php

declare(strict_types=1);

use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;

return new RetrieveCustomerResponse(
    status: null,
    reason: null,
    responseCode: 0,
    handle: 'test-dummy',
    organization: 'test-organization',
    vat: 'BE',
    firstName: 'Test',
    lastName: 'Dummy',
    gender: 'x',
    phone: '+32.093534200',
    email: 't.dummy@sandwave2.io',
    streetName: 'straat',
    streetNumber: 'straat 12',
    zip: '1001MH',
    city: 'city',
    countryCode: 'BE',
);
