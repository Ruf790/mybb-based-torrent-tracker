<?php

declare(strict_types=1);

if (!defined('IN_ADMIN_PANEL'))
{
    exit('<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

$lang->load('manage_avatars');

define('M_AVATARS', 'v.3.2');
define('AVATARS_PER_PAGE', 24);

require_once INC_PATH . '/functions_multipage.php';


/**
 * Substitute {1}, {2}… (and %1$s, %2$s… produced by $lang->load()) in a lang string
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $v) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$v;
            $map['%' . $n . '$s'] = (string)$v;
        }
        return $map ? strtr($str, $map) : $str;
    }
}

/**
 * Normalize an avatar path from DB or disk to a lookup key
 * ("./uploads/avatars/avatar_5.png?dateline=123" -> "avatar_5.png")
 */
function avatar_key(string $path): string
{
    $path = explode('?', $path, 2)[0];
    return strtolower(basename(str_replace('\\', '/', $path)));
}

/**
 * Scan image for embedded markup / code.
 * Looks for real tags and handlers, not bare words, to avoid false positives on binary data.
 */
function scan_image(string $file): bool
{
    global $_adir;
    $full = $_adir . $file;
    if (!is_file($full)) {
        return false;
    }

    $data = @file_get_contents($full);
    if ($data === false || $data === '') {
        return false;
    }

    $pattern = '#<\s*/?\s*(script|iframe|object|embed|applet|html|body|meta|link|style|form|svg)\b'
             . '|<\?php|<\?='
             . '|(java|vb)script\s*:'
             . '|\bon(load|error|click|focus|blur|mouse[a-z]+|key[a-z]+)\s*=#i';

    return !preg_match($pattern, $data);
}

/**
 * Get image dimensions and mime type
 */
function get_image_contents(string $file): array|false
{
    global $_adir;
    $info = @getimagesize($_adir . $file);
    if (!$info) {
        return false;
    }

    return [
        'width'  => (int)$info[0],
        'height' => (int)$info[1],
        'mime'   => (string)$info['mime'],
    ];
}

/**
 * Format file size
 */
function format_file_size(int|float $bytes): string
{
    global $lang;
    $units = [
        $lang->manage_avatars['unit_b'],
        $lang->manage_avatars['unit_kb'],
        $lang->manage_avatars['unit_mb'],
        $lang->manage_avatars['unit_gb'],
    ];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$asset_v = static fn(string $rel): string => (string)(@filemtime(TSDIR . $rel) ?: 1);

// Initialize
$_adir      = TSDIR . '/uploads/avatars/';
$_filetypes = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
$page       = max(1, (int)($_GET['page'] ?? 1));

// Avatar -> owners map (one query, used by both delete and display)
$owners = [];
$res = $db->sql_query_prepared("SELECT id, username, usergroup, avatar FROM users WHERE avatar <> ''");
while ($res && ($row = $db->fetch_array($res))) {
    $owners[avatar_key((string)$row['avatar'])][] = [
        'id'        => (int)$row['id'],
        'username'  => (string)$row['username'],
        'usergroup' => $row['usergroup'],
    ];
}


// ---------------------------------------------------------------------------
// POST: delete selected avatars (PRG)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_type'] ?? '') === 'delete') {
    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        exit($e($lang->manage_avatars['err_invalid_token']));
    }

    $ok = $skipped_shared = $not_found = $unlink_failed = [];
    $cleared  = 0;
    $selected = array_unique(array_map('strval', (array)($_POST['avatars'] ?? [])));

    $_adir_real = realpath($_adir);
    if ($_adir_real !== false && $selected) {
        $_adir_real .= DIRECTORY_SEPARATOR;
        require_once INC_PATH . '/functions_upload.php';

        foreach ($selected as $file) {
            // Only plain file names from the avatars directory
            if ($file === '' || basename($file) !== $file) {
                $not_found[] = $file;
                continue;
            }

            $real = realpath($_adir . $file);
            if ($real === false || !str_starts_with($real, $_adir_real) || !is_file($real)) {
                $not_found[] = $file;
                continue;
            }

            $users = $owners[avatar_key($file)] ?? [];

            // Shared by several accounts: leave file and profiles untouched
            if (count($users) > 1) {
                $skipped_shared[] = $file;
                continue;
            }

            // One owner: clear the profile first
            if ($users) {
                $uid = $users[0]['id'];
                $db->sql_query_prepared(
                    "UPDATE users SET avatar = '', avatardimensions = '', avatartype = '' WHERE id = ?",
                    [$uid]
                );
                $cleared++;

                if (function_exists('remove_avatars')) {
                    remove_avatars($uid);
                }
            }

            // Orphaned files (no owner) are deleted directly
            clearstatcache(true, $real);
            if (!is_file($real) || @unlink($real)) {
                $ok[] = $file;
            } else {
                $unlink_failed[] = $file;
            }
        }
    }

    if ($ok || $unlink_failed) {
        $who  = (string)($mybb->user['username'] ?? 'unknown');
        $list = implode(', ', array_slice($ok, 0, 20)) . (count($ok) > 20 ? ', ...' : '');
        write_log(sprintf(
            'Manage Avatars: %s deleted %d avatar(s), cleared %d profile(s), %d failed [%s]',
            $who, count($ok), $cleared, count($unlink_failed), $list
        ));
    }

    $_SESSION['ma_result'] = [
        'ok'             => $ok,
        'cleared'        => $cleared,
        'skipped_shared' => $skipped_shared,
        'not_found'      => $not_found,
        'unlink_failed'  => $unlink_failed,
    ];

    admin_redirect($_this_script_ . '&page=' . $page);
    exit;
}

// Flash result from previous POST
$result = null;
if (!empty($_SESSION['ma_result']) && is_array($_SESSION['ma_result'])) {
    $result = $_SESSION['ma_result'];
    unset($_SESSION['ma_result']);
}


// ---------------------------------------------------------------------------
// Load avatar files + global stats
// ---------------------------------------------------------------------------
$_avatars      = [];
$total_bytes   = 0;
$orphans_total = 0;

if (is_dir($_adir) && ($handle = opendir($_adir))) {
    while (false !== ($file = readdir($handle))) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $_filetypes, true)) {
            continue;
        }
        $_avatars[]   = $file;
        $total_bytes += (int)@filesize($_adir . $file);
        if (empty($owners[avatar_key($file)])) {
            $orphans_total++;
        }
    }
    closedir($handle);
}

natcasesort($_avatars);
$_avatars = array_values($_avatars);

// Paginate
$per_page     = AVATARS_PER_PAGE;
$total        = count($_avatars);
$pages        = max(1, (int)ceil($total / $per_page));
$page         = min($page, $pages);
$offset       = ($page - 1) * $per_page;
$avatars_page = array_slice($_avatars, $offset, $per_page);
$from         = $total > 0 ? $offset + 1 : 0;
$to           = min($offset + $per_page, $total);

// Build card data for this page
$items   = [];
$counts  = ['all' => 0, 'owned' => 0, 'orphan' => 0, 'flagged' => 0];

foreach ($avatars_page as $avatar) {
    $info    = get_image_contents($avatar);
    $clean   = scan_image($avatar);
    $bytes   = (int)@filesize($_adir . $avatar);
    $users   = $owners[avatar_key($avatar)] ?? [];
    $flagged = !$clean || !$info;

    $owner_html = '';
    if ($users) {
        $u = $users[0];
        $owner_html = '<a href="' . $e($BASEURL . '/' . get_profile_link($u['id'])) . '" class="ma-owner-link">'
                    . format_name($e($u['username']), $u['usergroup']) . '</a>';
    }

    $type = $info
        ? strtoupper(substr((string)strrchr($info['mime'], '/'), 1))
        : strtoupper(pathinfo($avatar, PATHINFO_EXTENSION));

    $items[] = [
        'file'       => $avatar,
        'hash'       => md5($avatar),
        'url'        => $BASEURL . '/uploads/avatars/' . rawurlencode($avatar),
        'size'       => format_file_size($bytes),
        'dims'       => $info ? $info['width'] . '×' . $info['height'] : null,
        'type'       => $type,
        'clean'      => $clean,
        'is_image'   => (bool)$info,
        'flagged'    => $flagged,
        'owners'     => count($users),
        'owner_html' => $owner_html,
    ];

    $counts['all']++;
    $counts[$users ? 'owned' : 'orphan']++;
    if ($flagged) {
        $counts['flagged']++;
    }
}

// JS strings: js_* keys without the prefix
$js_lang = [];
foreach ((array)$lang->manage_avatars as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $js_lang[substr((string)$k, 3)] = (string)$v;
    }
}


// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------
stdhead(ags_fmt($lang->manage_avatars['page_title'], M_AVATARS));

require_once INC_PATH . '/modals_images.php';

echo '<link rel="stylesheet" href="' . $e($BASEURL) . '/include/templates/default/style/sweetalert2.min.css">';
echo '<script src="' . $e($BASEURL) . '/scripts/sweetalert2.min.js"></script>';
echo '<script src="' . $e($BASEURL) . '/scripts/details_modal.js"></script>';
echo '<link rel="stylesheet" href="' . $e($BASEURL) . '/admin/templates/manage_avatars.css?v=' . $asset_v('/admin/templates/manage_avatars.css') . '">';

?>


<div class="container-lg my-4 ma-page">

    <!-- Header -->
    <div class="ma-header mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="ma-header-icon ma-tone-primary"><i class="fa-solid fa-user-astronaut"></i></div>
            <div>
                <h1 class="ma-title"><?= $e($lang->manage_avatars['sec_title']) ?></h1>
                <div class="ma-subtitle"><?= $e($lang->manage_avatars['sec_subtitle']) ?></div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="ma-range"><i class="fa-solid fa-layer-group me-1"></i><?= $e(ags_fmt($lang->manage_avatars['lbl_range'], $from, $to, $total)) ?></span>
            <span class="ma-chip ma-tone-muted"><i class="fa-solid fa-code-branch"></i><?= $e(M_AVATARS) ?></span>
        </div>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="ma-kpi">
                <div class="ma-kpi-icon ma-tone-primary"><i class="fa-solid fa-images"></i></div>
                <div>
                    <div class="ma-kpi-value"><?= number_format($total) ?></div>
                    <div class="ma-kpi-label"><?= $e($lang->manage_avatars['kpi_files']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ma-kpi">
                <div class="ma-kpi-icon ma-tone-info"><i class="fa-solid fa-hard-drive"></i></div>
                <div>
                    <div class="ma-kpi-value"><?= $e(format_file_size($total_bytes)) ?></div>
                    <div class="ma-kpi-label"><?= $e($lang->manage_avatars['kpi_disk']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ma-kpi">
                <div class="ma-kpi-icon ma-tone-warning"><i class="fa-solid fa-user-slash"></i></div>
                <div>
                    <div class="ma-kpi-value"><?= number_format($orphans_total) ?></div>
                    <div class="ma-kpi-label"><?= $e($lang->manage_avatars['kpi_orphans']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ma-kpi">
                <div class="ma-kpi-icon <?= $counts['flagged'] ? 'ma-tone-danger' : 'ma-tone-success' ?>">
                    <i class="fa-solid <?= $counts['flagged'] ? 'fa-shield-virus' : 'fa-shield-halved' ?>"></i>
                </div>
                <div>
                    <div class="ma-kpi-value"><?= $counts['flagged'] ?></div>
                    <div class="ma-kpi-label"><?= $e($lang->manage_avatars['kpi_flagged']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($result):
        $rows = [
            ['ok',             'fa-circle-check',         'ma-tone-success', $lang->manage_avatars['flash_deleted']],
            ['skipped_shared', 'fa-share-nodes',          'ma-tone-warning', $lang->manage_avatars['flash_skipped_shared']],
            ['not_found',      'fa-magnifying-glass',     'ma-tone-info',    $lang->manage_avatars['flash_not_found']],
            ['unlink_failed',  'fa-triangle-exclamation', 'ma-tone-danger',  $lang->manage_avatars['flash_unlink_failed']],
        ];
    ?>
    <div class="ma-result mb-4" role="status">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="fa-solid fa-clipboard-check me-2"></i><?= $e($lang->manage_avatars['sec_result']) ?></strong>
            <?php if (!empty($result['cleared'])): ?>
            <span class="ma-chip ma-tone-muted"><i class="fa-solid fa-user-xmark"></i><?= $e(ags_fmt($lang->manage_avatars['flash_profiles_cleared'], (int)$result['cleared'])) ?></span>
            <?php endif; ?>
        </div>
        <?php foreach ($rows as [$key, $icon, $tone, $label]):
            $list = (array)($result[$key] ?? []);
            if (!$list) continue;
        ?>
        <div class="ma-result-row">
            <div class="ma-kpi-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
            <div class="min-w-0">
                <div class="fw-semibold"><?= $e($label) ?> (<?= count($list) ?>)</div>
                <div class="ma-result-files">
                    <?= implode(', ', array_map($e, array_slice($list, 0, 8))) ?><?= count($list) > 8 ? ', …' : '' ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $e($_this_script_ . '&page=' . $page) ?>" id="avatarForm">
        <input type="hidden" name="my_post_key" value="<?= $e($mybb->post_code) ?>">
        <input type="hidden" name="action_type" value="delete">

        <?php if ($items): ?>
        <!-- Toolbar -->
        <div class="ma-toolbar">
            <label class="ma-selectall" for="select_all">
                <input class="form-check-input" type="checkbox" id="select_all">
                <i class="fa-solid fa-check-double"></i> <?= $e($lang->manage_avatars['lbl_select_all']) ?>
            </label>
            <div class="ma-filters" role="group" aria-label="<?= $e($lang->manage_avatars['aria_filters']) ?>">
                <button type="button" class="ma-filter active" data-filter="all">
                    <i class="fa-solid fa-border-all"></i><?= $e($lang->manage_avatars['opt_all']) ?> <span class="ma-count"><?= $counts['all'] ?></span>
                </button>
                <button type="button" class="ma-filter" data-filter="owned">
                    <i class="fa-solid fa-user-check"></i><?= $e($lang->manage_avatars['opt_owned']) ?> <span class="ma-count"><?= $counts['owned'] ?></span>
                </button>
                <button type="button" class="ma-filter" data-filter="orphan">
                    <i class="fa-solid fa-user-slash"></i><?= $e($lang->manage_avatars['opt_orphan']) ?> <span class="ma-count"><?= $counts['orphan'] ?></span>
                </button>
                <button type="button" class="ma-filter" data-filter="flagged">
                    <i class="fa-solid fa-shield-virus"></i><?= $e($lang->manage_avatars['opt_flagged']) ?> <span class="ma-count"><?= $counts['flagged'] ?></span>
                </button>
            </div>
        </div>

        <!-- Grid -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
            <?php foreach ($items as $it): ?>
            <div class="col ma-item"
                 data-owner="<?= $it['owners'] ? '1' : '0' ?>"
                 data-flag="<?= $it['flagged'] ? '1' : '0' ?>">
                <div class="ma-card<?= $it['flagged'] ? ' is-flagged' : '' ?>"
                     id="card_<?= $it['hash'] ?>"
                     tabindex="0"
                     aria-label="<?= $e($it['file']) ?>">

                    <div class="ma-thumb">
                        <img src="<?= $e($it['url']) ?>"
                             alt="<?= $e($it['file']) ?>"
                             loading="lazy"
                             data-bs-toggle="modal"
                             data-bs-target="#universalImageModal"
                             data-img-src="<?= $e($it['url']) ?>"
                             data-fallback="<?= $e($BASEURL) ?>/images/default_avatar.png">

                        <div class="ma-check">
                            <input type="checkbox" name="avatars[]"
                                   id="cb_<?= $it['hash'] ?>"
                                   value="<?= $e($it['file']) ?>"
                                   class="form-check-input"
                                   aria-label="<?= $e(ags_fmt($lang->manage_avatars['aria_select'], $it['file'])) ?>">
                        </div>

                        <div class="ma-badges">
                            <?php if (!$it['is_image']): ?>
                                <span class="ma-chip ma-tone-danger"><i class="fa-solid fa-file-circle-xmark"></i><?= $e($lang->manage_avatars['chip_not_image']) ?></span>
                            <?php elseif (!$it['clean']): ?>
                                <span class="ma-chip ma-tone-danger"><i class="fa-solid fa-bug"></i><?= $e($lang->manage_avatars['chip_suspicious']) ?></span>
                            <?php endif; ?>
                            <?php if ($it['owners'] > 1): ?>
                                <span class="ma-chip ma-tone-warning"><i class="fa-solid fa-share-nodes"></i><?= $e(ags_fmt($lang->manage_avatars['chip_shared'], $it['owners'])) ?></span>
                            <?php elseif (!$it['owners']): ?>
                                <span class="ma-chip ma-tone-warning"><i class="fa-solid fa-user-slash"></i><?= $e($lang->manage_avatars['chip_orphaned']) ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="ma-zoom"
                                data-bs-toggle="modal"
                                data-bs-target="#universalImageModal"
                                data-img-src="<?= $e($it['url']) ?>"
                                title="<?= $e($lang->manage_avatars['tip_zoom']) ?>" aria-label="<?= $e($lang->manage_avatars['tip_zoom']) ?>">
                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                        </button>
                    </div>

                    <div class="ma-body">
                        <div class="ma-file" title="<?= $e($it['file']) ?>">
                            <i class="fa-solid fa-file-image"></i><?= $e($it['file']) ?>
                        </div>

                        <div class="ma-meta">
                            <span title="<?= $e($lang->manage_avatars['tip_size']) ?>"><i class="fa-solid fa-weight-hanging"></i><?= $e($it['size']) ?></span>
                            <span title="<?= $e($lang->manage_avatars['tip_dims']) ?>"><i class="fa-solid fa-expand"></i><?= $e($it['dims'] ?? $lang->manage_avatars['lbl_na']) ?></span>
                            <span title="<?= $e($lang->manage_avatars['tip_type']) ?>"><i class="fa-solid fa-file-code"></i><?= $e($it['type']) ?></span>
                            <?php if ($it['clean'] && $it['is_image']): ?>
                            <span class="text-success-emphasis" title="<?= $e($lang->manage_avatars['tip_scan_ok']) ?>"><i class="fa-solid fa-shield-halved"></i><?= $e($lang->manage_avatars['lbl_clean']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="ma-owner">
                            <?php if ($it['owners']): ?>
                                <span class="ma-owner-icon ma-tone-primary"><i class="fa-solid fa-user"></i></span>
                                <?= $it['owner_html'] ?>
                                <?php if ($it['owners'] > 1): ?>
                                    <span class="text-body-secondary small text-nowrap"><?= $e(ags_fmt($lang->manage_avatars['lbl_more'], $it['owners'] - 1)) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ma-owner-icon ma-tone-muted"><i class="fa-solid fa-user-slash"></i></span>
                                <span class="text-body-secondary"><?= $e($lang->manage_avatars['lbl_no_owner']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="ma-empty mt-3 d-none" id="filterEmpty">
            <div class="ma-header-icon ma-tone-muted"><i class="fa-solid fa-filter-circle-xmark"></i></div>
            <div class="fw-semibold mb-1"><?= $e($lang->manage_avatars['empty_filter_title']) ?></div>
            <div class="small"><?= $lang->manage_avatars['empty_filter_hint'] ?></div>
        </div>

        <!-- Sticky action bar -->
        <div class="ma-actionbar">
            <div class="ma-actionbar-count">
                <i class="fa-solid fa-list-check me-2"></i><?= ags_fmt($e($lang->manage_avatars['lbl_selected']), '<span id="selectedCount">0</span>') ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-warning rounded-pill px-3" id="selectOrphans" <?= $counts['orphan'] ? '' : 'disabled' ?>>
                    <i class="fa-solid fa-user-slash me-2"></i><?= $e($lang->manage_avatars['btn_select_orphans']) ?>
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" id="clearSelection" disabled>
                    <i class="fa-solid fa-xmark me-2"></i><?= $e($lang->manage_avatars['btn_clear']) ?>
                </button>
                <button type="submit" class="btn btn-danger rounded-pill px-4" id="deleteSelected" disabled>
                    <i class="fa-solid fa-trash-can me-2"></i><?= $e($lang->manage_avatars['btn_delete']) ?>
                </button>
            </div>
        </div>

        <?php else: ?>
        <div class="ma-empty">
            <div class="ma-header-icon ma-tone-muted"><i class="fa-solid fa-folder-open"></i></div>
            <div class="fw-semibold mb-1"><?= $e(ags_fmt($lang->manage_avatars['empty_title'], '/uploads/avatars/')) ?></div>
            <div class="small"><?= $e($lang->manage_avatars['empty_hint']) ?></div>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($pages > 1): ?>
    <div class="ma-pagination">
        <?= multipage($total, $per_page, $page, $_this_script_) ?>
    </div>
    <?php endif; ?>
</div>

<script>
const AGS_LANG = <?= json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
</script>
<script src="<?= $e($BASEURL) ?>/admin/scripts/manage_avatars.js?v=<?= $asset_v('/admin/scripts/manage_avatars.js') ?>.2"></script>

<?php stdfoot(); ?>
