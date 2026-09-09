<?php

declare(strict_types=1);

namespace Tests\Domain\Mailer;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Mail\Mailer as ContractMailer;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Jobs\SendEmail;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Models\Template;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Domain\Ferry\Repositories\MigratedCustomersRepository;
use Waterfront\Domain\Mailer\LegacyMailer;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\Mailer\PayloadDeserializer;
use Waterfront\Domain\Mailer\PayloadSerializer;
use Waterfront\Domain\Mailer\TemplateRepository;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\Environment;
use Webmozart\Assert\Assert;

#[CoversClass(Mailer::class)]
class MailerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function emailHistoryExistsOneCustomer(): void
    {
        $template = new TemplateFactory()->createOne([
            'slug' => 'template-string',
            'type' => 'subscription_renewal',
        ]);

        self::assertNotNull($template->header);

        $customer = new CustomerFactory()->createOne();

        $mailer = self::createMock(ContractMailer::class);
        $mailer->expects(self::once())
            ->method('send');

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
            $mailer,
            self::resolve(EmailHistoryRepository::class),
            self::resolve(PayloadDeserializer::class),
            self::resolve(CustomerRepository::class),
            self::resolve(TranslatorInterface::class),
            self::createStub(Factory::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(Environment::class),
        );
        $legacyMailer->send($emailHistory->id);

        self::assertSame(ReceiverType::CUSTOMER, $emailHistory->receiver_type);
        self::assertSame($template->id, $emailHistory->template->id);
        self::assertNotNull($emailHistory->sent_at);
    }

    #[Test]
    public function emailHistoryExistsMultipleCustomersAndCc(): void
    {
        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCancelled::getTemplateSlug(),
            'type' => 'subscription_cancel',
        ]);

        $subscription = DomainSubscriptionDataProvider::subscription();

        $customer1 = new CustomerFactory()->createOne();
        $customer2 = new CustomerFactory()->createOne();
        $customer3 = new CustomerFactory()->createOne();

        $cc1 = new CustomerFactory()->createOne();
        $cc2 = new CustomerFactory()->createOne();
        $cc3 = new CustomerFactory()->createOne();

        $mailTemplate = new MailSubscriptionCancelled(
            $subscription->product->productGroup->name,
            $subscription->product->name,
            $subscription->domain ?? '',
            $subscription->end_date->format(DateTimeFormat::DUTCHNOTIME),
            'testCancelOption'
        );

        $jobMock = self::createMock(Dispatcher::class);
        $jobMock->expects(self::exactly(3))
            ->method('dispatch');

        $mailer = new Mailer(
            $jobMock,
            self::resolve(MigratedCustomersRepository::class),
            self::resolve(EmailHistoryRepository::class),
            self::resolve(TemplateRepository::class),
            self::resolve(PayloadSerializer::class),
            self::resolve(LoggerInterface::class),
            self::resolve(CustomerRepository::class),
        );
        $mailer->send([$customer1, $customer2, $customer3], $mailTemplate, [$cc1, $cc2, $cc3]);
    }

    #[Test]
    public function emailBlocked(): void
    {
        new TemplateFactory()->createOne([
            'slug' => 'template-string',
        ]);

        $mailer = $this->app->get(Mailer::class);

        $customer = new CustomerFactory()->createOne();

        $migratedCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => false,
        ]);
        $customer->migratedCustomers()->attach($migratedCustomer);

        $template = new class () implements MailTemplateInterface {
            public static function getTemplateSlug(): string
            {
                return 'template-string';
            }
        };
        $mailer->send([$customer], $template);

        Queue::assertNotPushed(SendEmail::class);
    }

    #[Test]
    public function emailSuccessfulMigrated(): void
    {
        new TemplateFactory()->createOne([
            'slug' => 'template-string',
        ]);

        $jobMock = self::createMock(Dispatcher::class);
        $jobMock->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (SendEmail $job): bool {
                self::assertTrue($job->afterCommit);

                return true;
            }));

        $mailer = new Mailer(
            $jobMock,
            self::resolve(MigratedCustomersRepository::class),
            self::resolve(EmailHistoryRepository::class),
            self::resolve(TemplateRepository::class),
            self::resolve(PayloadSerializer::class),
            self::resolve(LoggerInterface::class),
            self::resolve(CustomerRepository::class),
        );

        $customer = new CustomerFactory()->createOne();

        $migratedCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => true,
        ]);
        $migratedCustomer->customers()->attach($customer);

        $template = new class () implements MailTemplateInterface {
            public static function getTemplateSlug(): string
            {
                return 'template-string';
            }
        };
        $mailer->send([$customer], $template);
    }

    #[Test]
    public function blockedEmailWithNotBlockedEmail(): void
    {
        new TemplateFactory()->createOne([
            'slug' => 'template-string',
        ]);

        $jobMock = self::createMock(Dispatcher::class);
        $jobMock->expects(self::once())
            ->method('dispatch');

        $mailer = new Mailer(
            $jobMock,
            self::resolve(MigratedCustomersRepository::class),
            self::resolve(EmailHistoryRepository::class),
            self::resolve(TemplateRepository::class),
            self::resolve(PayloadSerializer::class),
            self::resolve(LoggerInterface::class),
            self::resolve(CustomerRepository::class),
        );
        $notBlockedCustomer = new CustomerFactory()->createOne();

        $migratedCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => true,
        ]);
        $notBlockedCustomer->migratedCustomers()->attach($migratedCustomer);

        $blockedCustomer = new CustomerFactory()->createOne();

        $migratedCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => false,
        ]);
        $blockedCustomer->migratedCustomers()->attach($migratedCustomer);

        $template = new class () implements MailTemplateInterface {
            public static function getTemplateSlug(): string
            {
                return 'template-string';
            }
        };
        $mailer->send([$notBlockedCustomer, $blockedCustomer], $template);
    }

    #[Test]
    public function sendEmailWithCc(): void
    {
        new TemplateFactory()->createOne([
            'slug' => 'template-string',
        ]);

        $customer = new CustomerFactory()->createOne([
            'first_name' => 'Henk',
            'last_name' => 'Tank',
            'email' => 'henk.tank@example.com',
        ]);

        $emailHistoryRecord = self::createStub(EmailHistory::class);
        $emailHistoryRecord->method('__get')->willReturn(1);

        $emailHistoryRepository = self::createMock(EmailHistoryRepository::class);
        $emailHistoryRepository->expects(self::once())
            ->method('createHistoryRecord')
            ->with(
                $customer,
                ReceiverType::CUSTOMER,
                self::isInstanceOf(Template::class),
                'hank@example.com, tank@example.com',
                self::isNull()
            )
            ->willReturn($emailHistoryRecord);

        $mailer = new Mailer(
            self::createStub(Dispatcher::class),
            self::resolve(MigratedCustomersRepository::class),
            $emailHistoryRepository,
            self::resolve(TemplateRepository::class),
            self::resolve(PayloadSerializer::class),
            self::resolve(LoggerInterface::class),
            self::resolve(CustomerRepository::class),
        );

        $cc = [new Recipient('Hank', 'hank@example.com', Uuid::uuid4()), new Recipient('Tank', 'tank@example.com', Uuid::uuid4())];

        $template = new class () implements MailTemplateInterface {
            public static function getTemplateSlug(): string
            {
                return 'template-string';
            }
        };
        $mailer->send([$customer], $template, $cc);
    }
}
