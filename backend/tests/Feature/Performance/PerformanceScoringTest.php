<?php

use App\Models\RatingScale;
use App\Services\Performance\PerformanceScoring;

/*
| Weighted scoring of performance reviews (bcmath strings, 2 decimals half up) and the
| mapping of an overall score to the final rating band of a scale.
*/

beforeEach(function () {
    $this->scoring = app(PerformanceScoring::class);
});

it('averages ratings by weight and skips unrated or weightless items', function () {
    expect($this->scoring->weighted([['4', '40'], ['3', '35'], ['5', '25']]))->toBe('3.90')
        ->and($this->scoring->weighted([['4', '50'], [null, '50']]))->toBe('4.00')
        ->and($this->scoring->weighted([['5', '0'], ['2', '10']]))->toBe('2.00')
        ->and($this->scoring->weighted([[null, '100']]))->toBeNull()
        ->and($this->scoring->weighted([]))->toBeNull();
});

it('rounds half up to two decimals', function () {
    // (4×1 + 3×2) / 3 = 3.3333…
    expect($this->scoring->weighted([['4', '1'], ['3', '2']]))->toBe('3.33')
        // (5×1 + 4×2) / 3 = 4.3333… ; (2×1 + 3×1)/2 = 2.5
        ->and($this->scoring->weighted([['2', '1'], ['3', '1']]))->toBe('2.50')
        ->and($this->scoring->weighted([['1', '1'], ['2', '1'], ['2', '1']]))->toBe('1.67');
});

it('combines the goals and competencies scores with the cycle split', function () {
    expect($this->scoring->overall('4.00', '3.00', '70', '30'))->toBe('3.70')
        ->and($this->scoring->overall('3.90', '4.25', '60', '40'))->toBe('4.04')
        // One side missing: the other counts 100 %.
        ->and($this->scoring->overall(null, '3.50', '70', '30'))->toBe('3.50')
        ->and($this->scoring->overall('2.75', null, '70', '30'))->toBe('2.75')
        ->and($this->scoring->overall('4.00', '2.00', '0', '100'))->toBe('2.00')
        ->and($this->scoring->overall(null, null, '70', '30'))->toBeNull();
});

it('maps a score to the highest level whose threshold it reaches', function () {
    $scale = RatingScale::factory()->create()->load('levels');

    expect($this->scoring->levelFor($scale, '0.80')->value)->toBe('1.00')
        ->and($this->scoring->levelFor($scale, '1.50')->value)->toBe('2.00')
        ->and($this->scoring->levelFor($scale, '2.49')->value)->toBe('2.00')
        ->and($this->scoring->levelFor($scale, '3.70')->value)->toBe('4.00')
        ->and($this->scoring->levelFor($scale, '4.50')->value)->toBe('5.00')
        ->and($this->scoring->levelFor($scale, '5.00')->value)->toBe('5.00')
        ->and($this->scoring->levelFor($scale, null))->toBeNull();
});

it('derives KPI progress from the actual value, capped at 100', function () {
    expect($this->scoring->kpiProgress('8', '12'))->toBe('66.67')
        ->and($this->scoring->kpiProgress('700', '600'))->toBe('100.00')
        ->and($this->scoring->kpiProgress('0', '50'))->toBe('0.00')
        ->and($this->scoring->kpiProgress('5', '0'))->toBeNull()
        ->and($this->scoring->kpiProgress(null, '10'))->toBeNull();
});

it('adds weights exactly', function () {
    expect($this->scoring->totalWeight(['33.33', '33.33', '33.34']))->toBe('100.00')
        ->and($this->scoring->totalWeight(['40', null, '35']))->toBe('75.00');
});
