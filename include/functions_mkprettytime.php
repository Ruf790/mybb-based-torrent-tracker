<?php

declare(strict_types=1);

/**
 * Pick the correct plural form:
 *
 * English: one|other
 * Russian: one|few|many
 */
function mkprettytime_plural(int $n, string $forms): string
{
    $f = explode('|', $forms);

    // Slavic: one|few|many
    if (count($f) >= 3) {
        $m10  = $n % 10;
        $m100 = $n % 100;

        if ($m10 === 1 && $m100 !== 11) {
            return $f[0];
        }

        if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) {
            return $f[1];
        }

        return $f[2];
    }

    // English: one|other
    return $n === 1
        ? $f[0]
        : ($f[1] ?? $f[0]);
}

function mkprettytime(
    int $seconds,
    array $options = []
): string {
    global $lang;

    $units = [
        'years'   => 31536000,
        'months'  => 2592000,
        'weeks'   => 604800,
        'days'    => 86400,
        'hours'   => 3600,
        'minutes' => 60,
        'seconds' => 1,
    ];

    $short = (bool) ($options['short'] ?? false);
    $maxUnits = $options['max_units'] ?? null;

    // Zero
    if ($seconds < 1) {
        return $short
            ? $lang->global['zero_short']
            : $lang->global['zero_full'];
    }

    $result = [];

    foreach ($units as $unit => $unitSeconds) {
        if ($seconds < $unitSeconds) {
            continue;
        }

        $count = intdiv($seconds, $unitSeconds);
        $seconds %= $unitSeconds;

        if ($short) {
            $result[] = $count . $lang->global['short_' . $unit];
        } else {
            $label = mkprettytime_plural(
                $count,
                $lang->global['unit_' . $unit]
            );

            $result[] = $count . ' ' . $label;
        }

        if ($maxUnits !== null && count($result) >= (int) $maxUnits) {
            break;
        }
    }

    return implode(', ', $result);
}