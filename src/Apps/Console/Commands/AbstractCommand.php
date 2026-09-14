<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

abstract class AbstractCommand extends Command
{
    /**
     * Write a string as standard output and the Laravel log.
     *
     * @param string                                                  $string
     * @param 'alert'|'comment'|'error'|'info'|'question'|'warn'|null $style
     * @param 8|16|32|64|128|256|'normal'|'quiet'|'v'|'vv'|'vvv'|null $verbosity
     */
    final public function line($string, $style = null, $verbosity = null): void
    {
        if ($this->getOutput()->isQuiet()) {
            // Don't write to the Laravel log.
            return;
        }

        $this->writeToLaravelLog($string, $style);

        parent::line(
            $string,
            $style,
            $verbosity,
        );
    }

    private function writeToLaravelLog(string $string, ?string $style = null): void
    {
        match ($style) {
            'warning' => Log::warning($string),
            'error' => Log::error($string),
            'question', 'comment' => Log::debug($string),
            default => Log::info($string),
        };
    }
}
