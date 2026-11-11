<?php

namespace App\Services\Attendance;

use App\Enums\PunchDirection;
use App\Services\Attendance\Data\PunchInput;

/**
 * Turns an ordered list of punches into in/out pairs.
 *
 * - Punches of unknown direction alternate: in when nothing is open, else out.
 * - A second "in" while one is open is ignored (the first arrival counts).
 * - A second "out" right after a pair moves that pair's end (the last departure counts).
 * - An "out" with nothing open before any pair is a missing check-in.
 * - An "in" left open at the end is a missing check-out.
 */
class PunchPairer
{
    /**
     * @param  list<PunchInput>  $punches  sorted by time, duplicates removed
     * @return array{pairs: list<array{0: PunchInput, 1: PunchInput}>, open_in: ?PunchInput, orphan_out: ?PunchInput, ignored: list<int>}
     */
    public function pair(array $punches): array
    {
        $pairs = [];
        $open = null;
        $orphanOut = null;
        $ignored = [];

        foreach ($punches as $punch) {
            $direction = $punch->direction === PunchDirection::Unknown
                ? ($open === null ? PunchDirection::In : PunchDirection::Out)
                : $punch->direction;

            if ($direction === PunchDirection::In) {
                if ($open !== null) {
                    $ignored[] = $punch->id;
                } else {
                    $open = $punch;
                }

                continue;
            }

            if ($open !== null) {
                $pairs[] = [$open, $punch];
                $open = null;
            } elseif ($pairs !== []) {
                $ignored[] = $pairs[array_key_last($pairs)][1]->id;
                $pairs[array_key_last($pairs)][1] = $punch;
            } elseif ($orphanOut === null) {
                $orphanOut = $punch;
            } else {
                $ignored[] = $orphanOut->id;
                $orphanOut = $punch;
            }
        }

        return ['pairs' => $pairs, 'open_in' => $open, 'orphan_out' => $orphanOut, 'ignored' => $ignored];
    }
}
