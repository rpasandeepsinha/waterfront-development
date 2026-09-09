<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Jobs\SendEmail;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Domain\Ferry\Repositories\MigratedCustomersRepository;
use Waterfront\Domain\Mailer\Exceptions\MailValidationException;
use Waterfront\Domain\Mailer\Jobs\HubspotEmailJob;
use Webmozart\Assert\Assert;

class Mailer implements MailerInterface
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly MigratedCustomersRepository $migratedCustomersRepository,
        private readonly EmailHistoryRepository $emailHistoryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly PayloadSerializer $payloadSerializer,
        private readonly LoggerInterface $logger,
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws MailValidationException
     * @throws JsonException
     */
    public function send(
        array $recipients,
        MailTemplateInterface $template,
        array $cc = []
    ): void {
        if ($recipients === []) {
            throw new MailValidationException('No recipient(s) given.');
        }

        $eligibleRecipients = $this->getEligibleRecipients($recipients);

        if ($eligibleRecipients === []) {
            return;
        }

        try {
            $templateModel = $this->templateRepository->getBySlug($template::getTemplateSlug());
        } catch (ModelNotFoundException) {
            $this->logger->notice(sprintf('Template not found for slug: %s', $template::getTemplateSlug()));
            return;
        }

        $this->logger->info(sprintf('Template by slug "%s" found.', $template::getTemplateSlug()));

        foreach ($eligibleRecipients as $recipient) {
            $emailHistoryRecord = $this->emailHistoryRepository->createHistoryRecord(
                $recipient,
                $this->getReceiverType($recipient),
                $templateModel,
                implode(', ', array_map(fn ($item) => $item->getEmail(), $cc)),
                $this->payloadSerializer->serialize($template)
            );

            $job = $templateModel->hubspot_template_id !== null ? new HubspotEmailJob($emailHistoryRecord->id) : new SendEmail($emailHistoryRecord->id);
            $job->afterCommit();
            $this->jobDispatcher->dispatch($job);
        }
    }

    public function resend(int $emailHistoryId): void
    {
        $emailHistory = $this->emailHistoryRepository->getById($emailHistoryId);
        Assert::string($emailHistory->receiver_email);
        Assert::uuid($emailHistory->receiver_uuid);

        $recipient = new Recipient('', $emailHistory->receiver_email, Uuid::fromString($emailHistory->receiver_uuid));

        if ($emailHistory->receiver_type === ReceiverType::CUSTOMER) {
            $customer = $this->customerRepository->findByUuid(Uuid::fromString($emailHistory->receiver_uuid));
            Assert::isInstanceOf($customer, Customer::class);
            $recipient = new Recipient($customer->getFirstName(), $emailHistory->receiver_email, Uuid::fromString($emailHistory->receiver_uuid));
        }

        $emailHistoryRecord = $this->emailHistoryRepository->createHistoryRecord(
            $recipient,
            $emailHistory->receiver_type,
            $emailHistory->template,
            $emailHistory->cc_emails ?? '',
            $emailHistory->payload
        );

        $job = $emailHistory->template->hubspot_template_id !== null ? new HubspotEmailJob($emailHistoryRecord->id) : new SendEmail($emailHistoryRecord->id);
        $this->jobDispatcher->dispatch($job);
    }

    private function getReceiverType(IsMailable $recipient): ReceiverType
    {
        if ($recipient instanceof Customer) {
            return ReceiverType::CUSTOMER;
        }

        if ($recipient->getUuid() !== null && $this->customerRepository->findByUuid($recipient->getUuid()) !== null) {
            return ReceiverType::CUSTOMER;
        }

        return ReceiverType::IDENTITY;
    }

    /**
     * @param IsMailable[] $recipients
     *
     * @return IsMailable[]
     */
    private function getEligibleRecipients(array $recipients): array
    {
        $eligibleRecipients = [];
        foreach ($recipients as $recipient) {
            $isEligible = $this->migratedCustomersRepository->isMigratedCustomerEligibleForMailing($recipient->getEmail());

            if (! $isEligible) {
                $this->logger->debug(
                    sprintf(
                        'Customer is not eligible for mailing yet. Email: %s',
                        $recipient->getEmail()
                    )
                );

                continue;
            }

            $eligibleRecipients[] = $recipient;
        }
        return $eligibleRecipients;
    }
}
