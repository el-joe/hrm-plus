<?php

namespace App\Services\Employees;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Thin wrapper around openspout/openspout (streaming XLSX / CSV, no
 * PhpSpreadsheet, low memory): read a sheet into header-keyed rows, write
 * rows to a file.
 */
class Spreadsheet
{
    /**
     * Rows of the first sheet keyed by the normalised header ("Branch code" => "branch_code").
     * Keys are 1-based spreadsheet line numbers (the header is line 1).
     *
     * @return array<int, array<string, string>>
     */
    public function read(string $path, string $extension, int $maxRows): array
    {
        $reader = strtolower($extension) === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);
        $rows = [];
        $headers = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    $values = array_map(fn ($value): string => $this->stringify($value), $row->toArray());

                    if ($headers === null) {
                        $headers = array_map(fn (string $header): string => $this->headerKey($header), $values);

                        continue;
                    }

                    if (implode('', $values) === '') {
                        continue;
                    }

                    if (count($rows) >= $maxRows) {
                        $rows[$line] = ['__too_many__' => '1'];

                        break 2;
                    }

                    $rows[$line] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), ''));
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function write(string $path, string $format, array $headers, iterable $rows): void
    {
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);

        if ($format === 'csv') {
            // openspout adds a UTF-8 byte order mark, so spreadsheet programs open Arabic text correctly.
            $writer->addRow(Row::fromValues($headers));
        } else {
            $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)));
        }

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value, $row)));
        }

        $writer->close();
    }

    public function headerKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))), '_');
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
            default => trim((string) $value),
        };
    }
}
