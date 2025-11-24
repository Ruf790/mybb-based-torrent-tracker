<?php

declare(strict_types=1);

function mkprettytime(
    int $seconds,
    array $options = []
): string {
    $units = [
        'years'   => 31536000,
        'months'  => 2592000,
        'weeks'   => 604800,
        'days'    => 86400,
        'hours'   => 3600,
        'minutes' => 60,
        'seconds' => 1,
    ];

    $labels = [
        'full' => [
            'years' => ' year', 'months' => ' month', 'weeks' => ' week',
            'days'  => ' day',  'hours'  => ' hour',  'minutes' => ' minute', 'seconds' => ' second',
        ],
        'plural' => [
            'years' => ' years', 'months' => ' months', 'weeks' => ' weeks',
            'days'  => ' days',  'hours'  => ' hours',  'minutes' => ' minutes', 'seconds' => ' seconds',
        ],
        'short' => [
            'years' => 'y', 'months' => 'mo', 'weeks' => 'w',
            'days' => 'd', 'hours' => 'h', 'minutes' => 'm', 'seconds' => 's',
        ],
    ];

    $short = (bool) ($options['short'] ?? false);
    $maxUnits = $options['max_units'] ?? null;

    if ($seconds < 1) {
        return $short ? '0s' : '0 seconds';
    }

    $result = [];

    foreach ($units as $unit => $unitSeconds) {
        if ($seconds < $unitSeconds) continue;

        $count = intdiv($seconds, $unitSeconds);
        $seconds %= $unitSeconds;

        $labelType = $short
            ? 'short'
            : ($count === 1 ? 'full' : 'plural');

        $result[] = "{$count}{$labels[$labelType][$unit]}";

        if ($maxUnits && count($result) >= $maxUnits) break;
    }

    return implode(', ', $result);
}

