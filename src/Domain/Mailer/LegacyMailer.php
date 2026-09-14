<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\View\Factory as ViewFactory;
use JsonException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\Environment;
use Webmozart\Assert\Assert;

class LegacyMailer
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly EmailHistoryRepository $emailHistoryRepository,
        private readonly PayloadDeserializer $payloadDeserializer,
        private readonly CustomerRepository $customerRepository,
        private readonly TranslatorInterface $translator,
        private readonly ViewFactory $viewFactory,
        private readonly ConfigurationInterface $configuration,
        private readonly Environment $environment,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function send(int $emailHistoryId): void
    {
        $emailHistory = $this->emailHistoryRepository->getById($emailHistoryId);
        $emailTemplate = $emailHistory->template;

        Assert::string($emailHistory->receiver_email);

        $customer = $emailHistory->receiver_type === ReceiverType::CUSTOMER
            ? $this->customerRepository->findByUuid(Uuid::fromString($emailHistory->receiver_uuid))
            : null;

        $data = $emailHistory->payload !== null ? $this->payloadDeserializer->deserialize($emailHistory->payload) : [];

        $body = $emailTemplate->body;
        if ($this->viewFactory->exists($body)) {
            $body = $this->viewFactory->make(view: $body, data: $data)->render();
        }

        $subject = $this->environment === Environment::PROD
            ? $emailTemplate->subject ?? ''
            : sprintf('%s (%s)', $emailTemplate->subject ?? '', $this->environment->value);

        $data = [
            'subject' => $subject,
            'header' => $subject,
            'body' => $body,
            'footer' => $emailTemplate->footer,
            'logoPath' => $this->getLogoPath(),
            'greeting' => $customer !== null
                ? $this->translator->translate('email.personalised_greetings', [
                    'firstName' => $customer->getFirstName(),
                ]) : null,
        ];

        $this->mailer->send(
            ['html' => 'email.layout'],
            $data,
            function (Message $message) use ($emailHistory, $emailTemplate, $customer): void {
                $message->subject($emailTemplate->subject);
                $message->to($emailHistory->receiver_email, $customer?->getFirstName());
                $message->cc($this->getCC($emailHistory));
                $message->getHeaders()->add(new MetadataHeader('template', $emailTemplate->slug));

                if ($customer !== null) {
                    $message->getHeaders()->add(new MetadataHeader('customer-id', (string) $customer->id));
                }
            },
        );

        $emailHistory->requested_at = CarbonImmutable::now();
        $emailHistory->save();
    }

    /**
     * @return string[]
     */
    private function getCC(EmailHistory $emailHistory): array
    {
        if (! is_string($emailHistory->cc_emails) || $emailHistory->cc_emails === '') {
            return [];
        }

        if (! str_contains($emailHistory->cc_emails, ',')) {
            return [$emailHistory->cc_emails];
        }

        return explode(',', $emailHistory->cc_emails);
    }

    private function getLogoPath(): string
    {
        return sprintf('/assets/email/images/default/%s.png', $this->configuration->getAsString('app.theme'));
    }
}
