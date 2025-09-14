<?php
/**
 * Преобразует количество секунд в человекочитаемое время
 *
 * @param int $seconds Количество секунд
 * @param array $options Опции: ['short' => true/false]
 * @return string
 */
function mkprettytime(int $seconds, array $options = []): string
{
    global $lang;

    $units = [
        'years'   => 365*24*60*60,
        'months'  => 30*24*60*60,
        'weeks'   => 7*24*60*60,
        'days'    => 24*60*60,
        'hours'   => 60*60,
        'minutes' => 60,
        'seconds' => 1
    ];

    $labels_full = [
        'years'   => ' year',   'months'  => ' month', 'weeks' => ' week',
        'days'    => ' day',    'hours'   => ' hour',  'minutes' => ' minute', 'seconds' => ' second'
    ];

    $labels_plural = [
        'years'   => ' years',   'months'  => ' months', 'weeks' => ' weeks',
        'days'    => ' days',    'hours'   => ' hours',  'minutes' => ' minutes', 'seconds' => ' seconds'
    ];

    $labels_short = [
        'years' => 'y', 'months' => 'mo', 'weeks' => 'w',
        'days' => 'd', 'hours' => 'h', 'minutes' => 'm', 'seconds' => 's'
    ];

    $result = [];

    foreach ($units as $unit => $unit_seconds) {
        if ($seconds >= $unit_seconds) {
            $count = intdiv($seconds, $unit_seconds);
            $seconds %= $unit_seconds;

            if (!empty($options['short'])) {
                $result[] = $count . ($labels_short[$unit] ?? $unit[0]);
            } else {
                $label = ($count === 1) ? ($labels_full[$unit] ?? $unit) : ($labels_plural[$unit] ?? $unit.'s');
                $result[] = "$count$label";
            }
        }
    }

    return !empty($result) ? implode(', ', $result) : ($options['short'] ? '0s' : '0 seconds');
}
