<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Mappers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Ferry\Mappers\CsvParser;

#[CoversClass(CsvParser::class)]
class CsvParserTest extends TestCase
{
    #[Test]
    public function parsesWithHeaders(): void
    {
        $csv = (string) file_get_contents(__DIR__ . '/data/parse_with_headers.csv');

        $parser = new CsvParser();
        $parsedCSV = $parser->parseCsvWithHeaders($csv);

        self::assertSame(
            [
                [
                    'header1' => 'value1',
                    'header2' => 'value2',
                    'header3' => 'value3',
                ],
            ],
            $parsedCSV
        );
    }
}
