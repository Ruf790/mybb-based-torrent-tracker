<?php

declare(strict_types=1);



// ── build_forum_jump ──────────────────────────────────────────────────────────
function build_forum_jump(
    int|string $pid        = 0,
    int|string $selitem    = 0,
    int|string $addselect  = 1,
    string     $depth      = '',
    int|string $showextras = 1,
    bool       $showall    = false,
    string     $permissions= '',
    string     $name       = 'fid'
): string {
    global $forum_cache, $jumpfcache, $permissioncache, $mybb;

    $pid        = (int)$pid;
    $selitem    = (int)$selitem;
    $addselect  = (int)$addselect;
    $showextras = (int)$showextras;

    if (!is_array($jumpfcache)) {
        if (!is_array($forum_cache)) {
            cache_forums();
        }
        foreach ($forum_cache as $forum) {
            if ($forum['active'] != 0) {
                $jumpfcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
            }
        }
    }

    if (!is_array($permissioncache)) {
        $permissioncache = forum_permissions();
    }

    $bits = '';

    if (isset($jumpfcache[$pid]) && is_array($jumpfcache[$pid])) {
        foreach ($jumpfcache[$pid] as $main) {
            foreach ($main as $forum) {
                $selected = $selitem === (int)$forum['fid'] ? ' selected="selected"' : '';
                $fname    = htmlspecialchars_uni(strip_tags($forum['name']));
                $bits    .= "<option value=\"{$forum['fid']}\"{$selected}>{$depth} {$fname}</option>";
                if (!empty($forum_cache[$forum['fid']])) {
                    $bits .= build_forum_jump($forum['fid'], $selitem, 0, $depth . '--', $showextras, $showall, $permissions, $name);
                }
            }
        }
    }

    if (!$addselect) {
        return $bits;
    }

    if ($showextras === 0) {
        return "<select name=\"{$name}\" class=\"form-select form-select-sm border pe-5 w-auto\">{$bits}</select>";
    }

    $forum_link = str_contains(FORUM_URL, '.html')
        ? "'" . str_replace('{fid}', "'+option+'", FORUM_URL) . "'"
        : "'" . str_replace('{fid}', "'+option", FORUM_URL);

    return <<<HTML
    <form action="forumdisplay.php" method="get">
        <select name="{$name}" class="form-select form-select-sm border pe-5 w-auto">
            <option value="-4">Private Messages</option>
            <option value="-3">User Control Panel</option>
            <option value="-5">Whos Online</option>
            <option value="-2">Search</option>
            <option value="-1">Forum Home</option>
            {$bits}
        </select>
        <button type="submit" class="btn btn-sm btn-primary rounded">
            <i class="fa-solid fa-shuffle"></i> &nbsp;Go
        </button>
    </form>
    <script>
    document.querySelector('select[name="{$name}"]').addEventListener('change', function() {
        const option = this.value;
        window.location = option < 0
            ? 'forumdisplay.php?fid=' + option
            : {$forum_link};
    });
    </script>
    HTML;
}