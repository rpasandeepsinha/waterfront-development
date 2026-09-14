<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\Products\UpdateProductsS3;
use Waterfront\Apps\Console\Commands\Translations\UpdateTranslationsS3;

#[AsCommand(name: 'export:all')]
#[Description('Runs all export:* commands exporting all items to object storage.')]
class UpdateObjectStorageItems extends Command
{
    public function handle(): int
    {
        $commands = [
            UpdateTranslationsS3::class,
            UpdateProductsS3::class,
        ];

        foreach ($commands as $command) {
            $this->info("Running $command");
            $exitCode = Artisan::call($command);

            if ($exitCode !== 0) {
                $this->error("$command failed with exit code: $exitCode");
            }
        }

        return self::SUCCESS;
    }
}
