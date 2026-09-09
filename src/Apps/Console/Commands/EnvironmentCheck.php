<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Dotenv\Parser\Entry;
use Dotenv\Parser\Parser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'environment:check')]
#[Description('Checks if all required environment variables are loaded')]
class EnvironmentCheck extends Command
{
    public function handle(): int
    {
        $loadedVariables = $this->getLoadedVariables();
        $requiredVariables = $this->getRequiredVariables();

        if (($diff = array_diff($requiredVariables, $loadedVariables)) !== []) {
            $this->output->warning('Missing environment variables:');
            $this->output->listing($diff);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getLoadedVariables(): array
    {
        return array_keys($_ENV);
    }

    /**
     * @return string[]
     */
    private function getRequiredVariables(): array
    {
        $fileName = $this->laravel->basePath() . DIRECTORY_SEPARATOR . '.env';
        $fileContent = file_get_contents($fileName);

        if ($fileContent === false) {
            throw new RuntimeException(sprintf('Cannot read .env file from path %s', $fileName));
        }

        $parser = new Parser();
        $parsedVariables = $parser->parse($fileContent);

        return array_map(fn (Entry $entry) => $entry->getName(), $parsedVariables);
    }
}
