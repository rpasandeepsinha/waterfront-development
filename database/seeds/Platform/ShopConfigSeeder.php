<?php

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\Seeder;
use RuntimeException;

class ShopConfigSeeder extends Seeder
{
    public function run(): void
    {
        $disk = $this->container->make(Factory::class)->disk('uiconfig');

        $filename = sprintf('%s/Data/%s', __DIR__, 'shop-config.json');
        if (! file_exists($filename)) {
            throw new RuntimeException(sprintf('Data file "%s" does not exist.', $filename));
        }

        /** @var string $contents */
        $contents = file_get_contents($filename);

        $disk->put('shop-config.json', $contents);
    }
}
