<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Email;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Email\Actions\CleanEmailHistoryAction;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[AsCommand(name: 'email-history:clean-payload')]
#[Description('Cleanup email history from gdpr values')]
class CleanEmailHistoryPayloadData extends Command
{
    public function handle(
        ConfigurationInterface $configuration,
        EmailHistoryRepository $emailHistoryRepository,
        CleanEmailHistoryAction $cleanEmailHistoryAction,
    ): int {
        $days = $configuration->getAsInteger('hubspot.email_history_retention_days');
        $beforeDate = CarbonImmutable::now()->subDays($days);

        foreach ($emailHistoryRepository->getBeforeDate($beforeDate) as $emailHistory) {
            $cleanEmailHistoryAction->execute($emailHistory);
        }

        return self::SUCCESS;
    }
}
