<?php

declare(strict_types=1);

// ── get_parent_list ───────────────────────────────────────────────────────────
function get_parent_list(int $fid): string
{
    global $forum_cache;
    static $forumarraycache;

    if (!empty($forumarraycache[$fid])) {
        return $forumarraycache[$fid]['parentlist'];
    }

    if (!empty($forum_cache[$fid])) {
        return $forum_cache[$fid]['parentlist'];
    }

    cache_forums();
    return $forum_cache[$fid]['parentlist'] ?? '';
}


// ── build_parent_list ─────────────────────────────────────────────────────────
function build_parent_list(int $fid, string $column = 'fid', string $joiner = 'OR', string $parentlist = ''): string
{
    if (!$parentlist) {
        $parentlist = get_parent_list($fid);
    }

    $parts = array_map(
        fn($val) => "{$column}='{$val}'",
        explode(',', $parentlist)
    );

    return '(' . implode(" {$joiner} ", $parts) . ')';
}