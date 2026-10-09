<?php
declare(strict_types=1);

// Strings live in the 'details' lang. These functions are also called from browse,
// so the lang is loaded on first use if the page hasn't done it.
function ags_bookmark_lang(): void
{
    global $lang;
    if (empty($lang->details)) {
        $lang->load('details');
    }
}

// $lang->load() turns {1} into %1$s, so both forms are substituted.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return $map ? strtr($str, $map) : $str;
    }
}


function return_torrent_bookmark_array(int $userid): array
{
    global $db;
    static $cache = [];

    if (isset($cache[$userid])) return $cache[$userid];

    $res  = $db->sql_query_prepared("SELECT torrentid FROM bookmarks WHERE userid = ?", [$userid]);
    $list = [];
    while ($row = $db->fetch_array($res)) {
        $list[] = (int)$row['torrentid'];
    }

    return $cache[$userid] = $list;
}

function get_torrent_bookmark_state(int $userid, int $torrentid, bool $text = false): string
{
    global $lang, $Torrent;

    ags_bookmark_lang();

    $bookmarked  = in_array($torrentid, return_torrent_bookmark_array($userid), true);
    $torrent_name = $Torrent['name']
        ? htmlspecialchars(cutename($Torrent['name'], 25))
        : htmlspecialchars($lang->details['unknown']);

    // Text-only mode
    if ($text) {
        return $bookmarked
            ? $lang->browse['title_delbookmark_torrent']
            : $lang->browse['title_bookmark_torrent'];
    }

    // Icon + popover
    if (!$bookmarked) {
        $pop_title = $lang->details['bm_title_off'];
        $pop_body  = '
            <div class="mb-2">
                <strong>' . htmlspecialchars($lang->details['bm_off_head']) . '</strong>
                <div class="small text-muted">' . htmlspecialchars($lang->details['bm_off_sub']) . '</div>
            </div>
            <div class="small"><i class="bi bi-link-45deg me-1"></i>' . $torrent_name . '</div>
            <button class="btn btn-warning btn-sm w-100 mt-2 add-bookmark-btn">
                <i class="bi bi-star me-1"></i>' . htmlspecialchars($lang->details['bm_btn_add']) . '
            </button>';
        $icon = '<i class="fa-regular fa-star fa-lg bookmark-icon" style="color:#ffc107"></i>';
    } else {
        $pop_title = $lang->details['bm_title_on'];
        $pop_body  = '
            <div class="mb-2">
                <strong>' . htmlspecialchars($lang->details['bm_on_head']) . '</strong>
                <div class="small text-muted">' . htmlspecialchars($lang->details['bm_on_sub']) . '</div>
            </div>
            <div class="small text-success"><i class="bi bi-check-circle me-1"></i>' . htmlspecialchars($lang->details['bm_on_note']) . '</div>
            <button class="btn btn-outline-danger btn-sm w-100 mt-2 remove-bookmark-btn">
                <i class="bi bi-trash me-1"></i>' . htmlspecialchars($lang->details['removebookmark']) . '
            </button>';
        $icon = '<i class="fa-solid fa-star fa-lg bookmark-icon bookmarked" style="color:#ffc107"></i>';
    }

    return '<a href="#" class="bookmark-toggle"'
         . ' data-torrent-id="' . $torrentid . '"'
         . ' data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true"'
         . ' data-bs-title="' . htmlspecialchars($pop_title, ENT_QUOTES) . '"'
         . ' data-bs-content="' . htmlspecialchars($pop_body, ENT_QUOTES) . '">'
         . $icon . '</a>';
}







function GetTorrentTags(array $t): string
{
    global $lang, $is_mod, $CURUSER;

    ags_bookmark_lang();

    // ── Popover builder ───────────────────────────────────────────────────────
    $pop = static function(string $title, string $body): string {
        return 'data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true"'
             . ' data-bs-trigger="hover focus"'
             . ' data-bs-title="' . htmlspecialchars($title, ENT_QUOTES) . '"'
             . ' data-bs-content="' . htmlspecialchars($body, ENT_QUOTES) . '"';
    };

    $benefit = static fn(string $icon, string $color, string $text): string =>
        '<div class="benefit-item"><i class="bi ' . $icon . ' text-' . $color . ' me-1"></i>'
        . '<span>' . $text . '</span></div>';   // $text is already escaped by the caller

    $wrap = static fn(string $body): string =>
        '<div class="torrent-feature-popover"><div class="feature-benefits">' . $body . '</div></div>';

    // ── Badge definitions ─────────────────────────────────────────────────────
    // [condition, popover_attrs, badge_html]
    $defs = [
        [
            $t['added'] > $CURUSER['last_login'],
            $pop($lang->details['tag_new_title'], $wrap(
                $benefit('bi-clock', 'success', ags_fmt(htmlspecialchars($lang->details['tag_new_added']), my_datee('relative', $t['added']))) .
                $benefit('bi-eye',   'success', htmlspecialchars($lang->details['tag_new_first']))
            )),
            '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">' . htmlspecialchars($lang->details['tag_new_badge']) . '</span>',
        ],
        [
            $t['free'] === 'yes',
            $pop($lang->details['tag_free_title'], $wrap(
                $benefit('bi-arrow-down-circle', 'success', htmlspecialchars($lang->details['tag_free_b1'])) .
                $benefit('bi-shield-check',      'success', htmlspecialchars($lang->details['tag_free_b2'])) .
                $benefit('bi-download',          'success', htmlspecialchars($lang->details['tag_free_b3']))
            )),
            '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">'
            . '<i class="bi bi-gift me-1"></i>' . htmlspecialchars($lang->details['tag_free_badge']) . '</span>',
        ],
        [
            $t['silver'] === 'yes',
            $pop($lang->details['tag_silver_title'], $wrap(
                $benefit('bi-percent', 'secondary', htmlspecialchars($lang->details['tag_silver_b1'])) .
                $benefit('bi-shield',  'secondary', htmlspecialchars($lang->details['tag_silver_b2']))
            )),
            '<span class="badge-silver" title="' . htmlspecialchars($lang->details['tag_silver_title'], ENT_QUOTES) . '"><i class="fas fa-star"></i></span>',
        ],		
		[
            $t['thirtypercent'] === 'yes',
            $pop($lang->details['tag_thirty_title'], $wrap(
                $benefit('bi-pie-chart-fill', 'secondary', htmlspecialchars($lang->details['tag_thirty_b1'])) .
                $benefit('bi-shield',         'secondary', htmlspecialchars($lang->details['tag_thirty_b2']))
            )),
            '<span class="badge bg-secondary bg-opacity-10 border border-secondary border-opacity-25 pulse-badge" style="color:#411749;border-color:#41174966 !important;--pulse-color:65,23,73;">'
            . '<i class="bi bi-pie-chart-fill me-1"></i>30%</span>',
        ],		
        [
            $t['isnuked'] === 'yes',
            $pop($lang->details['tag_nuked_title'], $wrap(
                $benefit('bi-exclamation-triangle', 'danger',  ags_fmt(htmlspecialchars($lang->details['tag_nuked_reason']), htmlspecialchars($t['WhyNuked'] ?? ''))) .
                $benefit('bi-info-circle',          'warning', htmlspecialchars($lang->details['tag_nuked_risk']))
            )),
            '<i class="fa-solid fa-circle-radiation fa-lg" style="color:#e70808"></i>',
        ],
        [
            $t['isrequest'] === 'yes',
            $pop($lang->details['tag_request_title'], $wrap(
                $benefit('bi-people',       'primary', htmlspecialchars($lang->details['tag_request_b1'])) .
                $benefit('bi-check-circle', 'primary', htmlspecialchars($lang->details['tag_request_b2']))
            )),
            '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">'
            . '<i class="bi bi-check-lg me-1"></i>' . htmlspecialchars($lang->details['tag_request_badge']) . '</span>',
        ],
        [
            $t['doubleupload'] === 'yes',
            $pop($lang->details['tag_double_title'], $wrap(
                $benefit('bi-lightning-charge', 'primary', htmlspecialchars($lang->details['tag_double_b1'])) .
                $benefit('bi-lightning-charge', 'primary', htmlspecialchars($lang->details['tag_double_b2'])) .
                $benefit('bi-graph-up-arrow',   'primary', htmlspecialchars($lang->details['tag_double_b3']))
            )),
            '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">'
            . '<i class="bi bi-lightning-charge me-1"></i>' . htmlspecialchars($lang->details['tag_double_badge']) . '</span>',
        ],
        [
            $t['sticky'] === 'yes',
            $pop($lang->details['tag_sticky_title'], $wrap(
                $benefit('bi-pin-angle', 'info', htmlspecialchars($lang->details['tag_sticky_b1'])) .
                $benefit('bi-star',      'info', htmlspecialchars($lang->details['tag_sticky_b2']))
            )),
            '<i class="fa-solid fa-bolt fa-lg" style="color:#0e5ce1"></i>',
        ],
    ];

    $I = [];
    foreach ($defs as [$cond, $attrs, $badge]) {
        if ($cond) $I[] = '<a href="#" class="badge-popover" ' . $attrs . '>' . $badge . '</a>';
    }



    return $I ? implode(' ', $I) : '';
}