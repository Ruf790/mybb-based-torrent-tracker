<?php
declare(strict_types=1);


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

    $bookmarked  = in_array($torrentid, return_torrent_bookmark_array($userid), true);
    $torrent_name = $Torrent['name']
        ? htmlspecialchars(cutename($Torrent['name'], 25))
        : 'Unknown';

    // Text-only mode
    if ($text) {
        return $bookmarked
            ? $lang->browse['title_delbookmark_torrent']
            : $lang->browse['title_bookmark_torrent'];
    }

    // Icon + popover
    if (!$bookmarked) {
        $pop_title = '⭐ Add to Bookmarks';
        $pop_body  = '
            <div class="mb-2">
                <strong>Save for later</strong>
                <div class="small text-muted">Quick access to this torrent</div>
            </div>
            <div class="small"><i class="bi bi-link-45deg me-1"></i>' . $torrent_name . '</div>
            <button class="btn btn-warning btn-sm w-100 mt-2 add-bookmark-btn">
                <i class="bi bi-star me-1"></i>Add to Bookmarks
            </button>';
        $icon = '<i class="fa-regular fa-star fa-lg bookmark-icon" style="color:#ffc107"></i>';
    } else {
        $pop_title = '✅ Bookmarked';
        $pop_body  = '
            <div class="mb-2">
                <strong>In Your Bookmarks</strong>
                <div class="small text-muted">Easily accessible anytime</div>
            </div>
            <div class="small text-success"><i class="bi bi-check-circle me-1"></i>Added to your collection</div>
            <button class="btn btn-outline-danger btn-sm w-100 mt-2 remove-bookmark-btn">
                <i class="bi bi-trash me-1"></i>Remove Bookmark
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


    // ── Popover builder ───────────────────────────────────────────────────────
    $pop = static function(string $title, string $body): string {
        return 'data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true"'
             . ' data-bs-trigger="hover focus"'
             . ' data-bs-title="' . $title . '"'
             . ' data-bs-content="' . htmlspecialchars($body, ENT_QUOTES) . '"';
    };

    $benefit = static fn(string $icon, string $color, string $text): string =>
        '<div class="benefit-item"><i class="bi ' . $icon . ' text-' . $color . ' me-1"></i>'
        . '<span>' . $text . '</span></div>';

    $wrap = static fn(string $body): string =>
        '<div class="torrent-feature-popover"><div class="feature-benefits">' . $body . '</div></div>';

    // ── Badge definitions ─────────────────────────────────────────────────────
    // [condition, popover_attrs, badge_html]
    $defs = [
        [
            $t['added'] > $CURUSER['last_login'],
            $pop('🆕 New Torrent', $wrap(
                $benefit('bi-clock', 'success', 'Added: ' . my_datee('relative', $t['added'])) .
                $benefit('bi-eye',   'success', 'Be the first to download')
            )),
            '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">New</span>',
        ],
        [
            $t['free'] === 'yes',
            $pop('🎁 Free Leech', $wrap(
                $benefit('bi-arrow-down-circle', 'success', 'Zero download counted') .
                $benefit('bi-shield-check',      'success', 'Ratio protection') .
                $benefit('bi-download',          'success', 'Risk-free downloading')
            )),
            '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">'
            . '<i class="bi bi-gift me-1"></i>Free</span>',
        ],
        [
            $t['silver'] === 'yes',
            $pop('🥈 Silver Leech', $wrap(
                $benefit('bi-percent', 'secondary', '50% download counted') .
                $benefit('bi-shield',  'secondary', 'Better ratio protection')
            )),
            '<span class="badge-silver" title="silverdownload"><i class="fas fa-star"></i></span>',
        ],		
		[
            $t['thirtypercent'] === 'yes',
            $pop('🟣 30% Leech', $wrap(
                $benefit('bi-pie-chart-fill', 'secondary', '30% download counted') .
                $benefit('bi-shield',         'secondary', 'Best ratio protection (besides Free)')
            )),
            '<span class="badge bg-secondary bg-opacity-10 border border-secondary border-opacity-25 pulse-badge" style="color:#411749;border-color:#41174966 !important;--pulse-color:65,23,73;">'
            . '<i class="bi bi-pie-chart-fill me-1"></i>30%</span>',
        ],		
        [
            $t['isnuked'] === 'yes',
            $pop('⚠️ Nuked Torrent', $wrap(
                $benefit('bi-exclamation-triangle', 'danger',  'Reason: ' . htmlspecialchars($t['WhyNuked'] ?? '')) .
                $benefit('bi-info-circle',          'warning', 'Download at your own risk')
            )),
            '<i class="fa-solid fa-circle-radiation fa-lg" style="color:#e70808"></i>',
        ],
        [
            $t['isrequest'] === 'yes',
            $pop('✅ Request Filled', $wrap(
                $benefit('bi-people',       'primary', 'Requested by community') .
                $benefit('bi-check-circle', 'primary', 'Successfully filled')
            )),
            '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">'
            . '<i class="bi bi-check-lg me-1"></i>Request</span>',
        ],
        [
            $t['doubleupload'] === 'yes',
            $pop('⚡ Double Upload', $wrap(
                $benefit('bi-lightning-charge', 'primary', '2x upload multiplier') .
                $benefit('bi-lightning-charge', 'primary', 'Faster ratio building') .
                $benefit('bi-graph-up-arrow',   'primary', 'Applies to all upload data')
            )),
            '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">'
            . '<i class="bi bi-lightning-charge me-1"></i>2x Upload</span>',
        ],
        [
            $t['sticky'] === 'yes',
            $pop('📌 Sticky Torrent', $wrap(
                $benefit('bi-pin-angle', 'info', 'Always visible') .
                $benefit('bi-star',      'info', 'Featured content')
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