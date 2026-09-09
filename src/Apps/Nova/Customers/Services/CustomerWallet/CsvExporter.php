<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Services\CustomerWallet;

use RuntimeException;

class CsvExporter
{
    /**
     * @param string[]     $headers
     * @param array<mixed> $dataSet
     */
    public function export(array $headers, array $dataSet): string
    {
        $csv = $this->openCsv();

        fputcsv($csv, $headers, escape: '\\');

        /** @var array<int|string, bool|float|int|string|null> $data */
        foreach ($dataSet as $data) {
            fputcsv($csv, $data, escape: '\\');
        }

        return $this->ripContent($csv);
    }

    /** @return resource */
    private function openCsv()
    {
        $csv = fopen('php://memory', 'r+');

        if (! is_resource($csv)) {
            throw new RuntimeException('Unable to open memory csv file.');
        }

        return $csv;
    }

    /** @param resource $csv */
    private function ripContent($csv): string
    {
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }
}
