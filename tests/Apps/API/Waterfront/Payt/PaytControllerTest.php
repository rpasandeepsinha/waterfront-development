<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Payt;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Webhooks\Controllers\PaytController;
use Waterfront\Apps\Webhooks\Services\Payt\PaytWebhookSignatureValidator;

#[CoversClass(PaytController::class)]
class PaytControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Config::set('financial.bu-payt.versio-2.secret', 'test-secret');
    }

    #[Test]
    public function signatureMatchesHandlesRequest(): void
    {
        $validator = $this->createStub(PaytWebhookSignatureValidator::class);
        $validator->method('validate')->willReturn(true);
        $this->app->bind(PaytWebhookSignatureValidator::class, fn () => $validator);

        $jsonString = file_get_contents(__DIR__ . '/data/invoice_new_comment_without_data_is_valid.json');
        assert($jsonString !== false);
        $payload = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('webhooks.payt.new-event', ['businessUnit' => 'versio-2']),
                $payload,
                ['X-PAYT-SIGNATURE' => 'any-signature'],
            )
            ->assertOk();
    }

    #[Test]
    public function signatureDoesNotMatchReturnsUnauthorized(): void
    {
        $validator = $this->createStub(PaytWebhookSignatureValidator::class);
        $validator->method('validate')->willReturn(false);
        $this->app->bind(PaytWebhookSignatureValidator::class, fn () => $validator);

        $jsonString = file_get_contents(__DIR__ . '/data/invoice_new_comment_without_data_is_valid.json');
        assert($jsonString !== false);
        $payload = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('webhooks.payt.new-event', ['businessUnit' => 'versio-2']),
                $payload,
                ['X-PAYT-SIGNATURE' => 'invalid'],
            )
            ->assertUnauthorized();
    }

    #[Test]
    public function unknownEventNameReturnsBadRequest(): void
    {
        $validator = $this->createStub(PaytWebhookSignatureValidator::class);
        $validator->method('validate')->willReturn(true);
        $this->app->bind(PaytWebhookSignatureValidator::class, fn () => $validator);

        $jsonString = file_get_contents(__DIR__ . '/data/unknown_event_is_invalid.json');
        assert($jsonString !== false);
        $payload = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('webhooks.payt.new-event', ['businessUnit' => 'versio-2']),
                $payload,
                ['X-PAYT-SIGNATURE' => 'any-signature'],
            )
            ->assertBadRequest();
    }

    #[Test]
    public function unknownBusinessUnitReturnsBadRequest(): void
    {
        $jsonString = file_get_contents(__DIR__ . '/data/invoice_new_comment_without_data_is_valid.json');
        assert($jsonString !== false);
        $payload = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('webhooks.payt.new-event', ['businessUnit' => 'unknown-bu']),
                $payload,
                ['X-PAYT-SIGNATURE' => 'any-signature'],
            )
            ->assertBadRequest();
    }

    #[Test]
    public function debtorNewCommentEventWillReturnSuccess(): void
    {
        $validator = $this->createStub(PaytWebhookSignatureValidator::class);
        $validator->method('validate')->willReturn(true);
        $this->app->bind(PaytWebhookSignatureValidator::class, fn () => $validator);

        $jsonString = file_get_contents(__DIR__ . '/data/debtor_new_comment.json');
        assert($jsonString !== false);
        $payload = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('webhooks.payt.new-event', ['businessUnit' => 'versio-2']),
                $payload,
                ['X-PAYT-SIGNATURE' => 'any-signature'],
            )
            ->assertOk();
    }

    #[Test]
    public function caseNewCommentEventWillReturnSuccess(): void
    {
        $validator = $this->createStub(PaytWebhookSignatureValidator::class);
        $validator->method('validate')->willReturn(true);
        $this->app->bind(PaytWebhookSignatureValidator::class, fn () => $validator);

        $jsonString = file_get_contents(__DIR__ . '/data/case_new_comment.json');
        assert($jsonString !== false);
        $payload = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('webhooks.payt.new-event', ['businessUnit' => 'versio-2']),
                $payload,
                ['X-PAYT-SIGNATURE' => 'any-signature'],
            )
            ->assertOk();
    }
}
