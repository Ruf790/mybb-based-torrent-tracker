<?php
declare(strict_types=1);

function flood_check(string $type = '', ?string $last = null, bool $shoutbox = false): ?string
{
    global $lang, $usergroups, $CURUSER;

    $floodlimit = (int) ($usergroups['floodlimit'] ?? 0);
    $timecut    = TIMENOW - $floodlimit;

    if ($last === null) {
        $last = '';
    }

    if (str_contains($last, '-')) {
        $last = strtotime($last);
    }

    if ($timecut <= $last && $floodlimit !== 0) {
        $remaining_time = $floodlimit - (TIMENOW - $last);

        if (!$shoutbox) {
            stderr(sprintf($lang->global['flooderror'], $floodlimit, $type, $remaining_time), false);
            return null;
        }

        return '<font color="#9f040b" size="2">'
             . sprintf($lang->global['flooderror'], $floodlimit, $type, $remaining_time)
             . '</font>';
    }

    return null;
}
