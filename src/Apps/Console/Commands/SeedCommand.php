<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Illuminate\Database\Console\Seeds\SeedCommand as LaravelSeedCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'db:seed')]
class SeedCommand extends LaravelSeedCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'scenario',
            null,
            InputOption::VALUE_OPTIONAL,
            'The name of the scenario you want to load',
            'default',
        );
    }
}
