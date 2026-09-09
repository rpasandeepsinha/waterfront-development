<?php

declare(strict_types=1);

namespace Tests\Domain\AuditLogs\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\AuditLogs\Actions\GetSubjectTypeAction;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(GetSubjectTypeAction::class)]
class GetSubjectTypeActionTest extends TestCase
{
    #[DataProvider('namespaceStrings')]
    #[Test]
    public function getSubjectType(string $namespace, string $expectedResult): void
    {
        $action = new GetSubjectTypeAction();
        self::assertSame($expectedResult, $action->execute($namespace));
    }

    /**
     * @return array<int, array<int,string>>
     */
    public static function namespaceStrings(): array
    {
        return [
            [Subscription::class, 'Subscription'],
            [DomainDeployment::class, 'DomainDeployment'],
            ['Modules\DomainService\Models\Subscription', 'DomainDeployment'],
            [SslDeployment::class, 'SslDeployment'],
            ['Modules\SslService\Models\Subscription', 'SslDeployment'],
        ];
    }
}
