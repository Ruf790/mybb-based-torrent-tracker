<?php

declare(strict_types=1);


// ── get_subscription_method ───────────────────────────────────────────────────
function get_subscription_method(int $tid = 0, array $postoptions = []): string
{
    global $db, $CURUSER;

    $methods = ['', 'none', 'email', 'pm'];
    $method  = max(0, (int)$CURUSER['subscriptionmethod']);

    if ($tid <= 0) {
        return $methods[$method] ?? '';
    }

    if (isset($postoptions['subscriptionmethod'])) {
        $m = trim($postoptions['subscriptionmethod']);
        return in_array($m, $methods, true) ? $m : '';
    }

    $query = $db->sql_query_prepared(
        "SELECT tid, notification FROM threadsubscriptions WHERE tid = ? AND uid = ? LIMIT 1",
        [$tid, (int)$CURUSER['id']]
    );
    $subscription = $query ? $db->fetch_array($query) : null;

    if ($subscription) {
        $method = (int)$subscription['notification'] + 1;
    }

    return $methods[$method] ?? '';
}
