<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

class CsvParser
{
    /**
     * @return array<array<string, string|null>>
     */
    public function parseCsvWithHeaders(string $csvContent): array
    {
        $lines = explode("\n", $csvContent);

        $rows = [];

        /** @var string[] $headers */
        $headers = str_getcsv(array_shift($lines), escape: '\\');

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            // Convert empty strings to null
            $rowData = array_map(
                fn ($value) => $value === '' ? null : $value,
                str_getcsv($line, escape: '\\'),
            );

            $rows[] = array_combine(
                $headers,
                $rowData,
            );
        }

        return $rows;
    }
}
