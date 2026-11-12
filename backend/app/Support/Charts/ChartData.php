<?php

namespace App\Support\Charts;

/**
 * Builds the configuration read by resources/js/ui/chart.js (Chart.js) and the
 * accessible data table rendered under every chart by <x-chart>. Values are numbers
 * (ints, or numeric strings for amounts — formatted for display only).
 *
 * Colours are design tokens (chart-1 … chart-6), never hex values.
 */
class ChartData
{
    /**
     * @param  'bar'|'line'|'doughnut'|'pie'  $type
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|float|string|null>, color?: string}>  $datasets
     * @param  array{stacked?: bool, horizontal?: bool, percent?: bool, decimals?: int, fill?: bool}  $options
     * @return array{type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|float|string|null>, color: string}>, options: array<string, mixed>}
     */
    public static function make(string $type, array $labels, array $datasets, array $options = []): array
    {
        $palette = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6'];

        return [
            'type' => $type,
            'labels' => array_values(array_map('strval', $labels)),
            'datasets' => array_values(array_map(fn (array $dataset, int $index): array => [
                'label' => $dataset['label'],
                'data' => array_values(array_map(fn ($value) => is_string($value) && is_numeric($value) ? (float) $value : $value, $dataset['data'])),
                'color' => $dataset['color'] ?? $palette[$index % count($palette)],
            ], $datasets, array_keys($datasets))),
            'options' => $options,
        ];
    }

    /**
     * Single-series helper.
     *
     * @param  array<string, int|float|string>  $values  label => value
     * @param  'bar'|'line'|'doughnut'|'pie'  $type
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function series(string $type, string $label, array $values, array $options = []): array
    {
        return self::make($type, array_map('strval', array_keys($values)), [['label' => $label, 'data' => array_values($values)]], $options);
    }

    /**
     * @param  array<string, mixed>|null  $chart
     */
    public static function isEmpty(?array $chart): bool
    {
        if ($chart === null || $chart['labels'] === []) {
            return true;
        }

        foreach ($chart['datasets'] as $dataset) {
            foreach ($dataset['data'] as $value) {
                if ((float) $value !== 0.0) {
                    return false;
                }
            }
        }

        return true;
    }
}
