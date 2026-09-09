<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Customers\Services\CustomerWallet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Apps\Nova\Customers\Services\CustomerWallet\CsvExporter;

#[CoversClass(CsvExporter::class)]
class CsvExporterTest extends TestCase
{
    #[Test]
    public function addsDataToCsv(): void
    {
        $content = new CsvExporter()->export(
            [
                'customer_number',
                'bank_account_name',
                'bank_account_number',
                'amount',
            ],
            [
               [ 123, 'B. Chungus', 'NL02ABNA0123456789', 1337],
            ],
        );

        self::assertSame('customer_number,bank_account_name,bank_account_number,amount
123,"B. Chungus",NL02ABNA0123456789,1337
', $content);
    }
}
