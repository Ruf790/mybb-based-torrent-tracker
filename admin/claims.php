<?php
declare(strict_types=1);

/**
 * Staff: Claims overview — who holds what, who is behind this month, removal without penalty.
 * Settings (norms, bonus, penalties) live in Settings → Claims (managesettings.php).
 */

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

global $mybb, $lang, $db, $CURUSER, $BASEURL, $_this_script_, $rootpath;

require_once INC_PATH . '/functions_claim.php';
$lang->load('claims_admin');
$L = $lang->claims_admin;

$ca_e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$ca_t = static function (string $key, string|int|float ...$args) use ($L): string {
    $map = [];
    foreach ($args as $i => $a) {
        $n = $i + 1;
        $map['{' . $n . '}']  = (string)$a;
        $map['%' . $n . '$s'] = (string)$a;
    }
    return strtr((string)($L[$key] ?? $key), $map);
};

$self = $_this_script_;   // index.php?act=claims

// ── POST: remove a claim without penalty (PRG) ──────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $back = $self . (($q = (string)($_POST['back'] ?? '')) !== '' && preg_match('/^[a-z0-9_=&%.\-+]*$/i', $q) ? '&' . $q : '');

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        flash_message($L['flash_csrf'], 'danger');
        admin_redirect($back);
    }

    if (($_POST['do'] ?? '') === 'remove') {
        $cid = (int)($_POST['claim_id'] ?? 0);
        $row = $db->fetch_array($db->sql_query_prepared(
            'SELECT c.id, c.uid, c.torrent_id, u.username, t.name AS torrent_name
             FROM claims c
             LEFT JOIN users u ON u.id = c.uid
             LEFT JOIN torrents t ON t.id = c.torrent_id
             WHERE c.id = ?',
            [$cid]
        ));
        if (!$row) {
            flash_message($L['flash_not_found'], 'danger');
            admin_redirect($back);
        }
        $db->sql_query_prepared('DELETE FROM claims WHERE id = ?', [$cid]);
        write_log(sprintf('[Claims] %s removed claim #%d of %s on torrent #%d (%s) without penalty',
            (string)$CURUSER['username'], $cid, (string)($row['username'] ?? '?'),
            (int)$row['torrent_id'], (string)($row['torrent_name'] ?? 'deleted')));
        flash_message($ca_t('flash_removed', (string)($row['username'] ?? '#' . (int)$row['uid'])), 'success');
        admin_redirect($back);
    }
    admin_redirect($back);
}

// ── Data: every claim with this month's progress (claim_progress — same rules as the cron) ──
$filter = (string)($_GET['f'] ?? 'all');
if (!in_array($filter, ['all', 'risk', 'reached', 'first'], true)) $filter = 'all';
$search = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$posterSel = claim_poster_select();
$q = $db->sql_query_prepared(
    "SELECT c.*, t.name AS torrent_name, t.size, t.seeders, {$posterSel},
            u.username, u.usergroup,
            (SELECT MAX(s.seedtime) FROM snatched s WHERE s.userid = c.uid AND s.torrentid = c.torrent_id) AS seedtime,
            (SELECT MAX(s.uploaded) FROM snatched s WHERE s.userid = c.uid AND s.torrentid = c.torrent_id) AS uploaded
     FROM claims c
     LEFT JOIN torrents t ON t.id = c.torrent_id
     LEFT JOIN users u ON u.id = c.uid
     ORDER BY c.added DESC, c.id DESC"
);

$all = [];
$kpi = ['total' => 0, 'users' => [], 'reached' => 0, 'risk' => 0, 'first' => 0];
while ($q && ($r = $db->fetch_array($q))) {
    $p = claim_progress($r);
    $p['state'] = $p['reached'] ? 'reached' : ($p['first_period'] ? 'first' : 'risk');
    $kpi['total']++;
    $kpi['users'][(int)$r['uid']] = true;
    $kpi[$p['state']]++;
    $all[] = $p;
}

$rows = array_values(array_filter($all, static function (array $p) use ($filter, $search): bool {
    if ($filter !== 'all' && $p['state'] !== $filter) return false;
    if ($search !== '') {
        $hay = mb_strtolower((string)($p['username'] ?? '') . ' ' . (string)($p['torrent_name'] ?? '') . ' #' . (int)$p['torrent_id']);
        if (!str_contains($hay, mb_strtolower($search))) return false;
    }
    return true;
}));
$count = count($rows);
$pages = max(1, (int)ceil($count / $perPage));
$page  = min($page, $pages);
$rows  = array_slice($rows, ($page - 1) * $perPage, $perPage);

$qs = static function (array $over = []) use ($filter, $search): string {
    $p = array_filter(array_merge(['f' => $filter !== 'all' ? $filter : '', 'q' => $search], $over), static fn($v) => $v !== '' && $v !== null);
    return $p ? http_build_query($p) : '';
};
$link = static fn(array $over = []): string => $self . (($s = $qs($over)) !== '' ? '&' . $s : '');


$monthStart = claim_month_start();
$nextStart  = claim_month_start($monthStart + 32 * 86400);
$daysLeft   = max(0, (int)ceil(($nextStart - TIMENOW) / 86400));
$seedNorm   = (int)claim_cfg('seed_hours');
$upTimes    = (int)claim_cfg('upload_times');

$e = $ca_e;
stdhead($L['title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/claims.css?v=<?= @filemtime($rootpath . '/admin/templates/claims.css') ?: 1 ?>">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>

<div class="container py-4 ca">

    <div class="ca-card ca-head mb-3">
        <span class="ca-head-icon ic-green"><i class="fa-solid fa-hand-holding-heart"></i></span>
        <div class="ca-head-text">
            <h1 class="ca-title"><?= $e($L['title']) ?></h1>
            <div class="ca-sub"><?= $e($ca_t('sub', $seedNorm, $upTimes, $daysLeft)) ?></div>
        </div>
        <div class="ca-head-actions">
            <?php if (!claim_enabled()): ?>
                <span class="ca-pill ca-pill--off"><i class="fa-solid fa-power-off"></i><?= $e($L['badge_disabled']) ?></span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= $BASEURL ?>/admin/managesettings.php#claim-settings"><i class="fa-solid fa-sliders me-1"></i><?= $e($L['btn_settings']) ?></a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-list-check',           'ic-blue',  'kpi_total',   number_format($kpi['total'])],
            ['fa-users',                'ic-slate', 'kpi_users',   number_format(count($kpi['users']))],
            ['fa-circle-check',         'ic-green', 'kpi_reached', number_format($kpi['reached'])],
            ['fa-triangle-exclamation', 'ic-red',   'kpi_risk',    number_format($kpi['risk'])],
        ] as [$icon, $tone, $key, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="ca-card ca-kpi">
                <span class="ca-kpi-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                <div><div class="ca-kpi-label"><?= $e($L[$key]) ?></div><div class="ca-kpi-value"><?= $val ?></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="ca-card">
        <div class="ca-toolbar">
            <nav class="ca-tabs" aria-label="<?= $e($L['aria_filter']) ?>">
                <?php foreach ([
                    'all'     => ['fa-layer-group', $kpi['total']],
                    'risk'    => ['fa-triangle-exclamation', $kpi['risk']],
                    'first'   => ['fa-hourglass-half', $kpi['first']],
                    'reached' => ['fa-circle-check', $kpi['reached']],
                ] as $f => [$icon, $n]): ?>
                    <a class="ca-tab<?= $filter === $f ? ' active' : '' ?>" href="<?= $e($link(['f' => $f === 'all' ? '' : $f, 'page' => ''])) ?>">
                        <i class="fa-solid <?= $icon ?>"></i><?= $e($L['f_' . $f]) ?><span class="ca-tab-n"><?= number_format($n) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="get" action="<?= $BASEURL ?>/admin/index.php" class="ca-search position-relative">
                <input type="hidden" name="act" value="claims">
                <?php if ($filter !== 'all'): ?><input type="hidden" name="f" value="<?= $e($filter) ?>"><?php endif; ?>
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="q" class="form-control form-control-sm" value="<?= $e($search) ?>" placeholder="<?= $e($L['ph_search']) ?>">
            </form>
        </div>

        <?php if (!$rows): ?>
            <div class="ca-empty"><i class="fa-solid fa-seedling"></i><p><?= $e($search !== '' || $filter !== 'all' ? $L['empty_filtered'] : $L['empty']) ?></p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="ca-table">
                <thead><tr>
                    <th><i class="fa-solid fa-user"></i><?= $e($L['th_user']) ?></th>
                    <th><i class="fa-solid fa-film"></i><?= $e($L['th_torrent']) ?></th>
                    <th><i class="fa-regular fa-calendar"></i><?= $e($L['th_claimed']) ?></th>
                    <th><i class="fa-solid fa-clock"></i><?= $e($ca_t('th_seeded', $seedNorm)) ?></th>
                    <th><i class="fa-solid fa-cloud-arrow-up"></i><?= $e($ca_t('th_uploaded', $upTimes)) ?></th>
                    <th><?= $e($L['th_status']) ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r):
                    [$tone, $label] = match ($r['state']) {
                        'reached' => ['success', $L['st_reached']],
                        'first'   => ['info',    $L['st_first']],
                        default   => ['danger',  $L['st_risk']],
                    };
                    $uname  = format_name($e((string)($r['username'] ?? ('#' . (int)$r['uid']))), (int)($r['usergroup'] ?? 1));
                    $warned = !empty($r['warned_at']) && (int)$r['warned_at'] >= $monthStart; ?>
                    <tr>
                        <td class="ca-nowrap"><a href="<?= $BASEURL ?>/<?= get_profile_link((int)$r['uid']) ?>" target="_blank"><?= $uname ?></a>
                            <div class="ca-muted"><a href="<?= $BASEURL ?>/claim.php?uid=<?= (int)$r['uid'] ?>" target="_blank"><?= $e($L['lnk_user_claims']) ?></a></div></td>
                        <td class="ca-name">
                            <?php if ($r['torrent_name'] === null): ?>
                                <span class="ca-muted"><?= $e($ca_t('torrent_deleted', (int)$r['torrent_id'])) ?></span>
                            <?php else: ?>
                                <a href="<?= $BASEURL ?>/<?= get_torrent_link((int)$r['torrent_id']) ?>" target="_blank"><?= $e((string)$r['torrent_name']) ?></a>
                                <div class="ca-muted"><?= mksize((float)$r['size']) ?> · <?= $e($ca_t('seeders', (int)$r['seeders'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="ca-nowrap"><?= date('d.m.Y', (int)$r['added']) ?></td>
                        <td class="ca-prog">
                            <div class="ca-bar"><div class="ca-bar-fill<?= $r['seed_pct'] >= 100 ? ' is-done' : '' ?>" style="width:<?= (int)$r['seed_pct'] ?>%"></div></div>
                            <span class="ca-muted"><?= number_format($r['seed_this_month'] / 3600, 1) ?> <?= $e($L['hours_short']) ?></span>
                        </td>
                        <td class="ca-prog">
                            <div class="ca-bar"><div class="ca-bar-fill<?= $r['up_pct'] >= 100 ? ' is-done' : '' ?>" style="width:<?= (int)$r['up_pct'] ?>%"></div></div>
                            <span class="ca-muted"><?= mksize((float)$r['up_this_month']) ?></span>
                        </td>
                        <td class="ca-nowrap">
                            <span class="ca-badge ca-badge--<?= $tone ?>"><?= $e($label) ?></span>
                            <?php if ($warned): ?><i class="fa-solid fa-bell ca-warned" title="<?= $e($ca_t('tip_warned', date('d.m.Y', (int)$r['warned_at']))) ?>"></i><?php endif; ?>
                        </td>
                        <td class="ca-nowrap text-end">
                            <form method="post" action="<?= $e($self) ?>" class="d-inline" data-ca-confirm="<?= $e($ca_t('confirm_remove', (string)($r['username'] ?? ''))) ?>">
                                <input type="hidden" name="my_post_key" value="<?= $e((string)$mybb->post_code) ?>">
                                <input type="hidden" name="do" value="remove">
                                <input type="hidden" name="claim_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="back" value="<?= $e($qs(['page' => $page > 1 ? $page : ''])) ?>">
                                <button class="btn btn-sm btn-outline-danger" title="<?= $e($L['tip_remove']) ?>"><i class="fa-solid fa-xmark me-1"></i><?= $e($L['btn_remove']) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
        <nav class="ca-pages">
            <?php for ($i = max(1, $page - 5); $i <= min($pages, $page + 5); $i++): ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= $e($link(['page' => $i > 1 ? $i : ''])) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
// Remove: SweetAlert2 confirmation, browser confirm() if Swal didn't load
document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-ca-confirm]') || f.dataset.ok === '1') return;
    e.preventDefault();
    var go = function () { f.dataset.ok = '1'; f.querySelector('button').disabled = true; f.submit(); };
    if (window.Swal) {
        window.Swal.fire({
            icon: 'warning', text: f.dataset.caConfirm, showCancelButton: true, reverseButtons: true, focusCancel: true,
            confirmButtonText: <?= json_encode($L['js_yes'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            cancelButtonText: <?= json_encode($L['js_cancel'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            confirmButtonColor: '#dc3545'
        }).then(function (r) { if (r.isConfirmed) go(); });
    } else if (window.confirm(f.dataset.caConfirm)) { go(); }
});
</script>
<?php
stdfoot();
