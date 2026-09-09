<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

foreach (File::files(__DIR__ . '/api') as $file) {
    require $file;
}
