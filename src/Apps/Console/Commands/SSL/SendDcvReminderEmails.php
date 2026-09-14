<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\SSL;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Contracts\Bus\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Domain\Mailer\TemplateRepository;
use Waterfront\Domain\Ssl\Jobs\SendDcvReminderEmail;
use Waterfront\Domain\Ssl\Mailers\SslRenewalFailedMissingCname;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository as SslDeploymentRepository;
use Waterfront\Domain\Ssl\Services\SslDnsManagementResolver;

#[AsCommand(name: 'ssl:dcv-reminders')]
#[Description('Queue daily reminders for SSL renewals pending due to missing/incorrect CNAME.')]
class SendDcvReminderEmails extends AbstractCommand
{
    public const REMINDER_WINDOW = 7;

    public function __construct(
        private readonly SslDeploymentRepository $sslDeploymentRepository,
        private readonly EmailHistoryRepository $emailHistoryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly SslDnsManagementResolver $sslDnsManagementResolver,
        private readonly Dispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = CarbonImmutable::today();

        $reminderCandidates = $this->sslDeploymentRepository->getReminderCandidates(self::REMINDER_WINDOW);

        $template = $this->templateRepository->getBySlug(
            SslRenewalFailedMissingCname::getTemplateSlug(),
        );

        $queued = 0;

        foreach ($reminderCandidates as $sslDeployment) {
            if ($this->sslDnsManagementResolver->hasManagedDns($sslDeployment)) {
                continue;
            }

            $customer = $sslDeployment->subscription->customer;

            $alreadySent = $this->emailHistoryRepository->wasEmailSentSince(
                receiverType: ReceiverType::CUSTOMER,
                receiverUuid: (string) $customer->getUuid(),
                templateId: $template->id,
                since: $today,
            );

            if ($alreadySent) {
                continue;
            }

            $this->dispatcher->dispatch(new SendDcvReminderEmail($sslDeployment->id));
            $queued++;
        }

        $this->line(sprintf('Reminder candidates queued: %d', $queued));

        return self::SUCCESS;
    }
}
