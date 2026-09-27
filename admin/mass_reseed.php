<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><b>Error!</b> Direct access to this file is not allowed.</div>');
}

define('MR_VERSION', 'v0.8');

require_once INC_PATH . '/datahandler.php';

class ReseedRequestHandler
{
    private const MAX_TORRENTS_PER_REQUEST = 100;
    private const LIST_LIMIT = 500;

    public function __construct(private array $config, private object $db, private array $curUser) {}

    public function handleRequest(): void
    {
        match ($_GET['do'] ?? $_POST['do'] ?? '') {
            'request_reseed_final' => $this->processReseedRequest(),
            'request_reseed'       => $this->showReseedForm(),
            default                => $this->showWeakTorrents(),
        };
    }

    private function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
    private function base(): string { return (string)($this->config['baseurl'] ?? $GLOBALS['BASEURL'] ?? ''); }

    // ═══ Отправка ═══════════════════════════════════════════════════
    private function processReseedRequest(): void
    {
        global $_this_script_;

        if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
            http_response_code(403);
            stderr('Security Error', 'Invalid security token. Please refresh the page and try again.');
        }

        $torrentIds  = $this->validateTorrentIds($_POST['torrents'] ?? '');
        $subject     = trim((string)($_POST['subject'] ?? ''));
        $message     = trim((string)($_POST['message'] ?? ''));
        $senderId    = (int)($_POST['sender'] ?? 0) === (int)($this->curUser['id'] ?? -1) ? (int)$this->curUser['id'] : 0;
        $requestFrom = ($_POST['requestfrom'] ?? 'owner') === 'all' ? 'all' : 'owner';
        $double      = ($_POST['doubleupload'] ?? '') === 'yes';

        if (!$torrentIds || $subject === '' || $message === '') {
            flash_message('Please fill in the subject and the message.', 'error');
            admin_redirect($_this_script_);
            exit;
        }

        require_once INC_PATH . '/functions_pm.php';
        $sent = $requestFrom === 'owner'
            ? $this->notifyUploaders($torrentIds, $subject, $message, $senderId)
            : $this->notifyAllSnatchers($torrentIds, $subject, $message, $senderId);

        if ($double && $sent > 0) {
            $this->enableDoubleUpload($torrentIds);
        }

        // Раньше рассылка нигде не фиксировалась
        write_log(sprintf('Mass Reseed: %s sent %d reseed PM(s) to %s for %d torrent(s)%s',
            $this->curUser['username'] ?? 'staff', $sent, $requestFrom === 'owner' ? 'uploaders' : 'snatchers', count($torrentIds), $double ? ', 2x upload enabled' : ''));

        flash_message($sent > 0
            ? "Reseed requests sent: {$sent} message(s) for " . count($torrentIds) . ' torrent(s).' . ($double ? ' Double upload enabled.' : '')
            : 'Nobody to notify — the selected torrents have no uploader or snatchers left.', $sent > 0 ? 'success' : 'warning');
        admin_redirect($_this_script_);
        exit;
    }

    private function idPlaceholders(array $ids): string { return implode(',', array_fill(0, count($ids), '?')); }

    private function notifyUploaders(array $ids, string $subject, string $message, int $senderId): int
    {
        $q = $this->db->sql_query_prepared("
            SELECT t.owner, t.name, t.id, u.username
            FROM torrents t INNER JOIN users u ON t.owner = u.id
            WHERE t.id IN ({$this->idPlaceholders($ids)}) AND t.seeders = 0 AND t.owner > 0 AND u.enabled = 'yes'
        ", array_values($ids));
        return $this->sendNotifications($q, $subject, $message, $senderId);
    }

    private function notifyAllSnatchers(array $ids, string $subject, string $message, int $senderId): int
    {
        $q = $this->db->sql_query_prepared("
            SELECT DISTINCT s.userid AS owner, s.torrentid AS id, t.name, u.username
            FROM snatched s
            INNER JOIN torrents t ON s.torrentid = t.id
            INNER JOIN users u ON s.userid = u.id
            WHERE s.finished = 'yes' AND s.torrentid IN ({$this->idPlaceholders($ids)}) AND s.userid > 0
              AND t.seeders = 0 AND u.enabled = 'yes'
            ORDER BY s.torrentid
        ", array_values($ids));
        return $this->sendNotifications($q, $subject, $message, $senderId);
    }

    private function sendNotifications(mixed $query, string $subject, string $message, int $senderId): int
    {
        // Раньше тип параметра был object — при ошибке запроса (false) падало с TypeError
        if (!$query) return 0;
        $sent = 0;
        while ($t = $this->db->fetch_array($query)) {
            $url = $this->base() . '/' . get_torrent_link((int)$t['id']);
            // Без htmlspecialchars: ЛС проходит через BBCode-парсер, который сам экранирует.
            // Раньше ники и названия с & или кавычками приходили как «&amp;», «&quot;»
            $body = str_replace(
                ['{username}', '{torrentname}'],
                [(string)$t['username'], '[url=' . $url . ']' . str_replace(['[', ']'], ['(', ')'], (string)$t['name']) . '[/url]'],
                $message
            );
            send_pm(['subject' => $subject, 'message' => $body, 'touid' => (int)$t['owner']], $senderId, true);
            $sent++;
        }
        return $sent;
    }

    private function enableDoubleUpload(array $ids): void
    {
        $this->db->sql_query_prepared("UPDATE torrents SET doubleupload = 'yes' WHERE id IN ({$this->idPlaceholders($ids)})", array_values($ids));
    }

    // ═══ Форма сообщения ════════════════════════════════════════════
    private function showReseedForm(): void
    {
        global $_this_script_;
        $raw = $_POST['torrents'] ?? [];
        $ids = $this->validateTorrentIds($raw);
        if (!$ids) {
            // Раньше ошибка печаталась голым блоком ДО шапки страницы
            flash_message('No torrents selected.', 'error');
            admin_redirect($_this_script_);
            exit;
        }
        $truncated = is_array($raw) && count(array_unique(array_filter(array_map('intval', $raw)))) > count($ids);

        $mass_reseed = [];
        @include 'include/staff_languages.php';

        stdhead('Request Reseed');
        $this->assets();
        $this->renderReseedForm($ids, $mass_reseed ?? [], $truncated);
        stdfoot();
        exit;
    }

    private function showWeakTorrents(): void
    {
        stdhead('Weak Torrents — Reseed');
        $this->assets();
        $this->renderWeakTorrentsTable();
        stdfoot();
    }

    private function head(string $icon, string $cls, string $title, string $sub, string $right = ''): void
    {
        echo '<div class="rs-card mb-3"><div class="rs-head"><span class="rs-head-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
           . '<div style="min-width:0"><h1 class="rs-title">' . $title . '</h1><div class="rs-sub">' . $sub . '</div></div>'
           . ($right !== '' ? '<div class="ms-auto d-flex flex-wrap gap-2 align-items-center">' . $right . '</div>' : '')
           . '</div></div>';
    }

    private function renderReseedForm(array $ids, array $lang, bool $truncated): void
    {
        global $mybb, $_this_script_;
        $ph = $this->idPlaceholders($ids);

        // Сколько сообщений уйдёт в каждом режиме — чтобы не разослать случайно сотни ЛС
        $q = $this->db->sql_query_prepared("SELECT COUNT(*) AS n FROM torrents t INNER JOIN users u ON t.owner = u.id WHERE t.id IN ({$ph}) AND t.seeders = 0 AND t.owner > 0 AND u.enabled = 'yes'", array_values($ids));
        $nOwners = (int)(($q ? $this->db->fetch_array($q) : [])['n'] ?? 0);
        $q = $this->db->sql_query_prepared("SELECT COUNT(DISTINCT s.userid, s.torrentid) AS n FROM snatched s INNER JOIN torrents t ON s.torrentid = t.id INNER JOIN users u ON s.userid = u.id WHERE s.finished = 'yes' AND s.torrentid IN ({$ph}) AND s.userid > 0 AND t.seeders = 0 AND u.enabled = 'yes'", array_values($ids));
        $nSnatch = (int)(($q ? $this->db->fetch_array($q) : [])['n'] ?? 0);

        $list = [];
        $q = $this->db->sql_query_prepared("SELECT id, name, times_completed, leechers FROM torrents WHERE id IN ({$ph}) ORDER BY name", array_values($ids));
        while ($q && ($r = $this->db->fetch_array($q))) $list[] = $r;

        $subject = $lang['message']['subject'] ?? 'Reseed request';
        $body    = $lang['message']['body'] ?? "Hello {username},\n\nthe torrent {torrentname} has no seeders right now. If you still have the files, please consider seeding it again.\n\nThank you!";
        $n = count($ids);
        ?>
<div class="container mt-3 mb-4 rs">
    <?php $this->head('fa-paper-plane', 'ic-green', 'Request reseed', 'Send a private message asking people to seed again',
        '<a href="' . $this->e($_this_script_) . '" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>Back to list</a>'); ?>

    <?php if ($truncated): ?>
    <div class="alert alert-warning d-flex gap-2 rounded-4"><i class="fa-solid fa-triangle-exclamation mt-1"></i><div>Only the first <?= self::MAX_TORRENTS_PER_REQUEST ?> selected torrents are included in one request.</div></div>
    <?php endif; ?>

    <form method="post" action="<?= $this->e($_this_script_) ?>" id="reseedForm" novalidate>
        <input type="hidden" name="my_post_key" value="<?= $this->e($mybb->post_code) ?>">
        <input type="hidden" name="do" value="request_reseed_final">
        <input type="hidden" name="torrents" value="<?= $this->e(implode(',', $ids)) ?>">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="rs-card h-100">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-blue"><i class="fa-solid fa-envelope"></i></span>
                        <div><h2 class="rs-sec-title">Message</h2><div class="rs-muted">One PM per recipient and torrent</div></div></div>
                    <div class="p-3 p-md-4">
                        <label class="form-label" for="rsSubject"><i class="fa-solid fa-heading"></i>Subject</label>
                        <input type="text" id="rsSubject" name="subject" class="form-control mb-3" value="<?= $this->e($subject) ?>" required maxlength="120">
                        <label class="form-label" for="rsMessage"><i class="fa-solid fa-align-left"></i>Text</label>
                        <textarea id="rsMessage" name="message" class="form-control" rows="9" required><?= $this->e($body) ?></textarea>
                        <div class="rs-tags mt-2">
                            <span class="rs-muted me-1">Insert:</span>
                            <button type="button" class="rs-tag" data-ins="{username}"><i class="fa-solid fa-user"></i>{username}</button>
                            <button type="button" class="rs-tag" data-ins="{torrentname}"><i class="fa-solid fa-link"></i>{torrentname}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="rs-card mb-3">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-amber"><i class="fa-solid fa-users"></i></span><h2 class="rs-sec-title">Recipients</h2></div>
                    <div class="p-3">
                        <label class="rs-opt mb-2"><input type="radio" name="requestfrom" value="owner" checked data-n="<?= $nOwners ?>">
                            <span class="rs-sec-icon ic-blue"><i class="fa-solid fa-user-pen"></i></span>
                            <span class="flex-grow-1"><b class="d-block">Uploaders only</b><small class="rs-muted"><?= number_format($nOwners) ?> message(s)</small></span></label>
                        <label class="rs-opt"><input type="radio" name="requestfrom" value="all" data-n="<?= $nSnatch ?>">
                            <span class="rs-sec-icon ic-purple"><i class="fa-solid fa-users"></i></span>
                            <span class="flex-grow-1"><b class="d-block">Everyone who snatched</b><small class="rs-muted"><?= number_format($nSnatch) ?> message(s)</small></span></label>
                    </div>
                </div>
                <div class="rs-card mb-3">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-slate"><i class="fa-solid fa-sliders"></i></span><h2 class="rs-sec-title">Options</h2></div>
                    <div class="p-3">
                        <label class="form-label" for="rsSender"><i class="fa-solid fa-user-tie"></i>Send as</label>
                        <select class="form-select mb-3" name="sender" id="rsSender">
                            <option value="0">System</option>
                            <option value="<?= (int)($this->curUser['id'] ?? 0) ?>"><?= $this->e($this->curUser['username'] ?? 'Me') ?></option>
                        </select>
                        <label class="rs-opt" for="rsDouble">
                            <span class="rs-sec-icon ic-amber"><i class="fa-solid fa-angles-up"></i></span>
                            <span class="flex-grow-1"><b class="d-block">Double upload</b><small class="rs-muted">Reward reseeders with 2× upload</small></span>
                            <input type="hidden" name="doubleupload" value="no">
                            <input class="form-check-input m-0" type="checkbox" role="switch" id="rsDouble" name="doubleupload" value="yes" style="width:2.5em;height:1.35em">
                        </label>
                    </div>
                </div>
                <div class="rs-card">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-red"><i class="fa-solid fa-magnet"></i></span>
                        <h2 class="rs-sec-title"><?= $n ?> torrent<?= $n === 1 ? '' : 's' ?></h2></div>
                    <ul class="rs-mini">
                        <?php foreach ($list as $t): ?>
                        <li><i class="fa-solid fa-magnet text-body-secondary"></i><span class="text-truncate"><?= $this->e($t['name']) ?></span><span class="rs-muted ms-auto text-nowrap"><i class="fa-solid fa-circle-check me-1"></i><?= (int)$t['times_completed'] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="rs-card rs-bar">
            <span class="rs-muted"><i class="fa-solid fa-envelope me-1"></i><b id="rsCount"><?= number_format($nOwners) ?></b> message(s) will be sent</span>
            <button type="submit" class="btn btn-success px-4" id="rsSend"><i class="fa-solid fa-paper-plane me-1"></i>Send requests</button>
        </div>
    </form>
</div>

<link rel="stylesheet" href="<?= $this->e($this->base()) ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $this->e($this->base()) ?>/scripts/sweetalert2.min.js"></script>
<script>
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const form = $('reseedForm'), msg = $('rsMessage');
    const note = '\n\nBonus: you get double upload credit for seeding this torrent again!';
    const n = () => parseInt(document.querySelector('[name="requestfrom"]:checked').dataset.n, 10) || 0;

    document.querySelectorAll('[name="requestfrom"]').forEach(r => r.addEventListener('change', () => { $('rsCount').textContent = n().toLocaleString(); }));
    $('rsDouble').addEventListener('change', function () {
        if (this.checked && !msg.value.includes(note)) msg.value += note;
        if (!this.checked) msg.value = msg.value.replace(note, '');
    });
    document.querySelectorAll('.rs-tag').forEach(b => b.addEventListener('click', () => {
        const s = msg.selectionStart ?? msg.value.length, e = msg.selectionEnd ?? s;
        msg.value = msg.value.slice(0, s) + b.dataset.ins + msg.value.slice(e);
        msg.focus(); msg.selectionStart = msg.selectionEnd = s + b.dataset.ins.length;
    }));

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const count = n(), dbl = $('rsDouble').checked;
        if (count === 0) { window.Swal ? Swal.fire({ title: 'Nobody to notify', text: 'Try “Everyone who snatched”.', icon: 'info' }) : alert('Nobody to notify.'); return; }
        let ok;
        if (window.Swal) {
            ok = (await Swal.fire({
                title: 'Send ' + count.toLocaleString() + ' message(s)?', icon: 'question',
                html: '<div class="text-start small text-body-secondary"><i class="fa-solid fa-magnet me-1"></i><?= $n ?> torrent(s)'
                    + (dbl ? '<br><i class="fa-solid fa-angles-up me-1 text-warning"></i>Double upload will be enabled' : '') + '</div>',
                showCancelButton: true, reverseButtons: true, confirmButtonText: '<i class="fa-solid fa-paper-plane me-1"></i>Send',
                confirmButtonColor: '#16a34a', cancelButtonColor: '#6c757d',
            })).isConfirmed;
        } else ok = confirm('Send ' + count + ' message(s)?');
        if (!ok) return;
        $('rsSend').disabled = true; $('rsSend').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
        if (window.Swal) Swal.fire({ title: 'Sending…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        HTMLFormElement.prototype.submit.call(form);
    });
})();
</script>
        <?php
    }

    // ═══ Список «слабых» торрентов ══════════════════════════════════
    private function renderWeakTorrentsTable(): void
    {
        global $_this_script_;

        $sort  = in_array($_GET['sort'] ?? '', ['added', 'snatched', 'leechers', 'name'], true) ? $_GET['sort'] : 'snatched';
        $order = ['added' => 't.added DESC', 'snatched' => 't.times_completed DESC, t.added DESC', 'leechers' => 't.leechers DESC, t.times_completed DESC', 'name' => 't.name ASC'][$sort];

        $sq = $this->db->sql_query_prepared("SELECT COUNT(*) AS n, COALESCE(SUM(leechers > 0),0) AS waiting, COALESCE(SUM(times_completed),0) AS snatched FROM torrents WHERE seeders = 0 AND visible = 'yes'");
        $st = $sq ? $this->db->fetch_array($sq) : [];

        $res = $this->db->sql_query_prepared("
            SELECT t.id, t.name, t.seeders, t.leechers, t.times_completed, t.added, t.owner, t.size,
                   u.username, u.usergroup
            FROM torrents t LEFT JOIN users u ON t.owner = u.id
            WHERE t.seeders = 0 AND t.visible = 'yes'
            ORDER BY {$order}
            LIMIT " . self::LIST_LIMIT);
        $rows = [];
        while ($res && ($r = $this->db->fetch_array($res))) $rows[] = $r;

        $base = $this->base();
        $self = (string)$_this_script_;
        $sortUrl = fn(string $s) => $this->e($self . (str_contains($self, '?') ? '&' : '?') . 'sort=' . $s);
        ?>
<div class="container mt-3 mb-4 rs">
    <?php $this->head('fa-seedling', 'ic-green', 'Mass Reseed', 'Torrents without seeders — ask uploaders or snatchers to seed again',
        '<span class="rs-ver"><i class="fa-solid fa-code-branch me-1"></i>' . MR_VERSION . '</span>'); ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-skull',          'ic-red',    'Dead torrents',       number_format((int)($st['n'] ?? 0))],
            ['fa-hourglass-half', 'ic-amber',  'Leechers waiting',    number_format((int)($st['waiting'] ?? 0))],
            ['fa-circle-check',   'ic-blue',   'Past snatches',       number_format((int)($st['snatched'] ?? 0))],
            ['fa-layer-group',    'ic-purple', 'Per request',         'up to ' . self::MAX_TORRENTS_PER_REQUEST],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="rs-card rs-kpi"><span class="rs-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="rs-kpi-label"><?= $label ?></div><div class="rs-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= $this->e($self) ?>" id="torrentSelectionForm">
        <input type="hidden" name="do" value="request_reseed">
        <div class="rs-card overflow-hidden">
            <div class="rs-toolbar">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="rs-muted me-1"><i class="fa-solid fa-arrow-down-wide-short me-1"></i>Sort:</span>
                    <?php foreach (['snatched' => ['fa-circle-check', 'Most snatched'], 'leechers' => ['fa-hourglass-half', 'Leechers waiting'], 'added' => ['fa-calendar', 'Newest'], 'name' => ['fa-font', 'Name']] as $k => [$ic, $lbl]): ?>
                    <a href="<?= $sortUrl($k) ?>" class="rs-sort<?= $sort === $k ? ' active' : '' ?>"><i class="fa-solid <?= $ic ?>"></i><?= $lbl ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="position-relative rs-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" id="rsFilter" placeholder="Filter by name…"></div>
            </div>
<?php if (!$rows): ?>
            <div class="rs-empty"><i class="fa-solid fa-circle-check text-success"></i><div class="fw-semibold">No dead torrents</div><div class="small">Every visible torrent has at least one seeder.</div></div>
<?php else: ?>
            <div class="table-responsive"><table class="rs-table">
                <thead><tr>
                    <th style="width:40px"><input class="form-check-input" type="checkbox" id="selectAllTorrents" aria-label="Select all"></th>
                    <th><i class="fa-solid fa-magnet"></i>Torrent</th>
                    <th><i class="fa-solid fa-user"></i>Uploader</th>
                    <th class="text-center"><i class="fa-solid fa-hourglass-half"></i>Leech</th>
                    <th class="text-center"><i class="fa-solid fa-circle-check"></i>Snatched</th>
                    <th class="text-end"></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $t):
                    $tid = (int)$t['id']; $le = (int)$t['leechers']; $sn = (int)$t['times_completed']; ?>
                    <tr data-name="<?= $this->e(mb_strtolower((string)$t['name'])) ?>" class="<?= $le > 0 ? 'is-wait' : '' ?>">
                        <td><input class="form-check-input torrent-checkbox" type="checkbox" name="torrents[]" value="<?= $tid ?>" aria-label="Select"></td>
                        <td>
                            <a href="<?= $this->e($base . '/' . get_torrent_link($tid)) ?>" class="rs-tname" target="_blank"><?= $this->e($t['name']) ?></a>
                            <div class="rs-muted"><i class="fa-solid fa-hard-drive me-1"></i><?= mksize((float)($t['size'] ?? 0)) ?> · <i class="fa-solid fa-calendar ms-1 me-1"></i><?= my_datee('relative', (int)$t['added']) ?></div>
                        </td>
                        <td><?= $t['username'] !== null
                            ? '<a href="' . $this->e($base . '/' . get_profile_link((int)$t['owner'])) . '" class="text-decoration-none">' . format_name($this->e($t['username']), (int)$t['usergroup']) . '</a>'
                            : '<em class="text-body-secondary">deleted</em>' ?></td>
                        <td class="text-center"><span class="rs-pill <?= $le > 0 ? 't-amber' : 't-slate' ?>"><?= number_format($le) ?></span></td>
                        <td class="text-center"><a href="<?= $this->e($base . '/viewsnatches.php?id=' . $tid) ?>" class="rs-pill <?= $sn > 0 ? 't-blue' : 't-slate' ?> text-decoration-none" title="View snatches"><?= number_format($sn) ?></a></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= $this->e($base . '/upload.php?id=' . $tid) ?>" class="rs-act" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="<?= $this->e($base . '/admin/index.php?act=fastdelete&id=' . $tid) ?>" class="rs-act danger rs-del" title="Delete" data-name="<?= $this->e($t['name']) ?>"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if (count($rows) >= self::LIST_LIMIT): ?><div class="rs-muted text-center py-2 border-top">Showing the first <?= self::LIST_LIMIT ?> — change the sort order to see others.</div><?php endif; ?>
<?php endif; ?>
        </div>

        <?php if ($rows): ?>
        <div class="rs-card rs-bar">
            <span class="rs-muted"><i class="fa-solid fa-square-check me-1"></i><b id="selectedCount">0</b> selected <span class="ms-1">(max <?= self::MAX_TORRENTS_PER_REQUEST ?>)</span></span>
            <button type="submit" class="btn btn-success px-4" id="submitButton" disabled><i class="fa-solid fa-seedling me-1"></i>Request reseed</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<link rel="stylesheet" href="<?= $this->e($base) ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $this->e($base) ?>/scripts/sweetalert2.min.js"></script>
<script>
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const boxes = () => [...document.querySelectorAll('.torrent-checkbox')].filter(b => !b.closest('tr').hidden);
    const MAX = <?= self::MAX_TORRENTS_PER_REQUEST ?>;
    function sync() {
        const all = [...document.querySelectorAll('.torrent-checkbox')], n = all.filter(b => b.checked).length;
        if ($('selectedCount')) $('selectedCount').textContent = n;
        if ($('submitButton')) $('submitButton').disabled = n === 0;
        const sa = $('selectAllTorrents'), vis = boxes();
        if (sa) { sa.checked = vis.length > 0 && vis.every(b => b.checked); sa.indeterminate = vis.some(b => b.checked) && !sa.checked; }
    }
    document.addEventListener('change', e => { if (e.target.classList?.contains('torrent-checkbox')) sync(); });
    $('selectAllTorrents')?.addEventListener('change', function () { boxes().forEach(b => { b.checked = this.checked; }); sync(); });
    // Клик по строке ставит галочку
    document.querySelector('.rs-table tbody')?.addEventListener('click', e => {
        if (e.target.closest('a, button, input')) return;
        const cb = e.target.closest('tr')?.querySelector('.torrent-checkbox');
        if (cb) { cb.checked = !cb.checked; sync(); }
    });
    $('rsFilter')?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.rs-table tbody tr').forEach(tr => { tr.hidden = q !== '' && !tr.dataset.name.includes(q); });
        sync();
    });
    document.querySelectorAll('.rs-del').forEach(a => a.addEventListener('click', async e => {
        e.preventDefault();
        const go = window.Swal
            ? (await Swal.fire({ title: 'Delete torrent?', text: a.dataset.name, icon: 'warning', showCancelButton: true, reverseButtons: true, focusCancel: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626', cancelButtonColor: '#6c757d' })).isConfirmed
            : confirm('Delete torrent ' + a.dataset.name + '?');
        if (go) location.href = a.href;
    }));
    $('torrentSelectionForm')?.addEventListener('submit', e => {
        const n = [...document.querySelectorAll('.torrent-checkbox')].filter(b => b.checked).length;
        if (n === 0) { e.preventDefault(); return; }
        if (n > MAX && window.Swal) {
            e.preventDefault();
            Swal.fire({ title: 'Too many selected', text: 'Only the first ' + MAX + ' will be included. Continue?', icon: 'info', showCancelButton: true, confirmButtonText: 'Continue' })
                .then(r => { if (r.isConfirmed) HTMLFormElement.prototype.submit.call(e.target); });
        }
        // Раньше здесь был ещё один confirm() — хотя дальше открывается форма, где всё подтверждается
    });
    sync();
})();
</script>
        <?php
    }

    private function assets(): void
    {
        echo '<link rel="stylesheet" href="' . $this->e($this->base()) . '/admin/templates/mass_reseed.css">' . "\n";
    }


    private function validateTorrentIds(mixed $input): array
    {
        $ids = is_array($input) ? array_map('intval', $input) : array_map('intval', explode(',', (string)$input));
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
        return array_slice($ids, 0, self::MAX_TORRENTS_PER_REQUEST);
    }
}

(new ReseedRequestHandler(['baseurl' => $BASEURL ?? ''], $db, $CURUSER ?? []))->handleRequest();