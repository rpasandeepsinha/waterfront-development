<?php

declare(strict_types=1);

namespace Tests\Domain\Mailer;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\View\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;
use Tests\Factories\CustomerFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Domain\Mailer\LegacyMailer;
use Waterfront\Domain\Mailer\PayloadDeserializer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\Environment;
use Webmozart\Assert\Assert;

#[CoversClass(LegacyMailer::class)]
class LegacyMailerTest extends IntegrationTestCase
{
    #[Test]
    public function sendEmailAddsMetadataHeaders(): void
    {
        $customer = new CustomerFactory()->createOne();
        $template = new TemplateFactory()->createOne(['slug' => 'templateslug']);

        $mailerMock = self::createMock(Mailer::class);
        $mailerMock
            ->expects(self::once())
            ->method('send')
            ->with(
                self::anything(),
                self::anything(),
                self::callback(function (Closure $closure) use ($template, $customer) {
                    $message = new Message(new Email());
                    $closure($message);
                    self::assertContainsEquals(
                        new MetadataHeader('template', $template->slug),
                        $message->getHeaders()->all(),
                    );
                    self::assertContainsEquals(
                        new MetadataHeader('customer-id', (string) $customer->id),
                        $message->getHeaders()->all(),
                    );

                    return true;
                }),
            );

        $emailHistory = new EmailHistory();
        $emailHistory->uuid = Uuid::uuid4()->toString();
        $emailHistory->sent_at = CarbonImmutable::now();
        $emailHistory->receiver_email = $customer->getEmail();
        $emailHistory->receiver_type = ReceiverType::CUSTOMER;
        Assert::isInstanceOf($customer->getUuid(), UuidInterface::class);
        $emailHistory->receiver_uuid = $customer->getUuid()->toString();
        $emailHistory->template_id = $template->id;
        $emailHistory->save();

        $legacyMailer = new LegacyMailer(
            $mailerMock,
            self::resolve(EmailHistoryRepository::class),
            self::resolve(PayloadDeserializer::class),
            self::resolve(CustomerRepository::class),
            self::resolve(TranslatorInterface::class),
            self::createStub(Factory::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(Environment::class),
        );
        $legacyMailer->send($emailHistory->id);
    }
}
