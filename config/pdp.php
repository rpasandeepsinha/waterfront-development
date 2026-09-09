<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'public_suffix_url' => Env::get('PUBLIC_SUFFIX_URL', 'https://publicsuffix.org/list/public_suffix_list.dat'),
];
