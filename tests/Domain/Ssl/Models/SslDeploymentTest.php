<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Ssl\Models\SslDeployment;

#[CoversClass(SslDeployment::class)]
class SslDeploymentTest extends TestCase
{
    #[Test]
    public function getStatusAttributeReturnsStatusFromValidJson(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = (string) json_encode(['certificate_status' => 'ISSUED']);

        self::assertSame('ISSUED', $deployment->status);
    }

    #[Test]
    public function getStatusAttributeReturnsNotFoundWhenKeyMissing(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = (string) json_encode(['other_key' => 'value']);

        self::assertSame('NOT_FOUND', $deployment->status);
    }

    #[Test]
    public function getStatusAttributeReturnsUnknownWhenLastResultIsNull(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = null;

        self::assertSame('UNKNOWN', $deployment->status);
    }

    #[Test]
    public function getStatusAttributeReturnsUnknownWhenLastResultIsInvalidJson(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = 'not-valid-json{{{';

        self::assertSame('UNKNOWN', $deployment->status);
    }

    #[Test]
    public function getDnsRecordAttributeReturnsTrueFromValidJson(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = (string) json_encode(['dns_record' => true]);

        self::assertTrue($deployment->dns_record);
    }

    #[Test]
    public function getDnsRecordAttributeReturnsFalseWhenKeyMissing(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = (string) json_encode(['other_key' => 'value']);

        self::assertFalse($deployment->dns_record);
    }

    #[Test]
    public function getDnsRecordAttributeReturnsFalseWhenLastResultIsNull(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = null;

        self::assertFalse($deployment->dns_record);
    }

    #[Test]
    public function getDnsRecordAttributeReturnsFalseWhenLastResultIsInvalidJson(): void
    {
        $deployment = new SslDeployment();
        $deployment->last_result = 'not-valid-json{{{';

        self::assertFalse($deployment->dns_record);
    }
}
