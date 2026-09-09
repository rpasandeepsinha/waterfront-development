<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Infra\HubspotClient\Enum\EmailSendResult;
use Waterfront\Infra\HubspotClient\Enum\EmailSendStatus;

/**
 * @extends Factory<EmailHistory>
 */
class EmailHistoryFactory extends Factory
{
    protected $model = EmailHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'receiver_type' => ReceiverType::CUSTOMER,
            'receiver_uuid' => Uuid::uuid4(),
            'cc_emails' => $this->faker->email(),
            'payload' => null,
            'sent_at' => CarbonImmutable::now(),
            'receiver_email' => $this->faker->email(),
            'hubspot_id' => (string) $this->faker->randomNumber(),
            'hubspot_status' => EmailSendStatus::COMPLETE->value,
            'last_result' => json_encode(EmailSendResult::SENT->value),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public function withTemplate(array $data = []): EmailHistoryFactory
    {
        $template = new TemplateFactory()->createOne($data);
        return $this->state(fn (): array => [
            'template_id' => $template->id,
        ]);
    }
}
