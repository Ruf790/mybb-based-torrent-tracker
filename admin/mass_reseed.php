<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><b>Error!</b> Direct access to this file is not allowed.</div>');
}

global $lang;
$lang->load('mass_reseed');

define('MR_VERSION', 'v0.9');

require_once INC_PATH . '/datahandler.php';

// {1}, {2}… → аргументы. $lang->load() превращает {1} в %1$s, поэтому подставляем оба формата
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}'] = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return strtr($str, $map);
    }
}

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

    // Строки js_* → AGS_LANG без префикса
    private function jsLang(): void
    {
        global $lang;
        $arr = [];
        foreach ($lang->mass_reseed as $k => $v) {
            if (str_starts_with((string)$k, 'js_')) $arr[substr((string)$k, 3)] = $v;
        }
        echo '<script>const AGS_LANG = ' . json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . "\n";
    }

    // ═══ Отправка ═══════════════════════════════════════════════════
    private function processReseedRequest(): void
    {
        global $_this_script_, $lang;

        if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
            http_response_code(403);
            stderr($lang->mass_reseed['err_security_title'], $lang->mass_reseed['err_security']);
        }

        $torrentIds  = $this->validateTorrentIds($_POST['torrents'] ?? '');
        $subject     = trim((string)($_POST['subject'] ?? ''));
        $message     = trim((string)($_POST['message'] ?? ''));
        $senderId    = (int)($_POST['sender'] ?? 0) === (int)($this->curUser['id'] ?? -1) ? (int)$this->curUser['id'] : 0;
        $requestFrom = ($_POST['requestfrom'] ?? 'owner') === 'all' ? 'all' : 'owner';
        $double      = ($_POST['doubleupload'] ?? '') === 'yes';

        // Текст не менялся: шаблон можно подставить на языке получателя.
        // textarea отдаёт переводы строк как \r\n, поэтому нормализуем перед сравнением.
        $norm = static fn(string $s): string => trim(str_replace("\r\n", "\n", $s));
        $useDefault = $norm($subject) === $norm((string)$lang->mass_reseed['msg_subject'])
                   && $norm($message) === $norm((string)$lang->mass_reseed['msg_body']);

        if (!$torrentIds || $subject === '' || $message === '') {
            flash_message($lang->mass_reseed['flash_fill'], 'error');
            admin_redirect($_this_script_);
            exit;
        }

        require_once INC_PATH . '/functions_pm.php';
        $sent = $requestFrom === 'owner'
            ? $this->notifyUploaders($torrentIds, $subject, $message, $senderId, $useDefault)
            : $this->notifyAllSnatchers($torrentIds, $subject, $message, $senderId, $useDefault);

        if ($double && $sent > 0) {
            $this->enableDoubleUpload($torrentIds);
        }

        // Раньше рассылка нигде не фиксировалась
        write_log(sprintf('Mass Reseed: %s sent %d reseed PM(s) to %s for %d torrent(s)%s',
            $this->curUser['username'] ?? 'staff', $sent, $requestFrom === 'owner' ? 'uploaders' : 'snatchers', count($torrentIds), $double ? ', 2x upload enabled' : ''));

        flash_message($sent > 0
            ? ags_fmt($lang->mass_reseed['flash_sent'], number_format($sent), count($torrentIds)) . ($double ? ' ' . $lang->mass_reseed['flash_sent_double'] : '')
            : $lang->mass_reseed['flash_nobody'], $sent > 0 ? 'success' : 'warning');
        admin_redirect($_this_script_);
        exit;
    }

    private function idPlaceholders(array $ids): string { return implode(',', array_fill(0, count($ids), '?')); }

    private function notifyUploaders(array $ids, string $subject, string $message, int $senderId, bool $useDefault): int
    {
        $q = $this->db->sql_query_prepared("
            SELECT t.owner, t.name, t.id, u.username, u.language
            FROM torrents t INNER JOIN users u ON t.owner = u.id
            WHERE t.id IN ({$this->idPlaceholders($ids)}) AND t.seeders = 0 AND t.owner > 0 AND u.enabled = 'yes'
        ", array_values($ids));
        return $this->sendNotifications($q, $subject, $message, $senderId, $useDefault);
    }

    private function notifyAllSnatchers(array $ids, string $subject, string $message, int $senderId, bool $useDefault): int
    {
        $q = $this->db->sql_query_prepared("
            SELECT DISTINCT s.userid AS owner, s.torrentid AS id, t.name, u.username, u.language
            FROM snatched s
            INNER JOIN torrents t ON s.torrentid = t.id
            INNER JOIN users u ON s.userid = u.id
            WHERE s.finished = 'yes' AND s.torrentid IN ({$this->idPlaceholders($ids)}) AND s.userid > 0
              AND t.seeders = 0 AND u.enabled = 'yes'
            ORDER BY s.torrentid
        ", array_values($ids));
        return $this->sendNotifications($q, $subject, $message, $senderId, $useDefault);
    }

    private function sendNotifications(mixed $query, string $subject, string $message, int $senderId, bool $useDefault): int
    {
        // Раньше тип параметра был object — при ошибке запроса (false) падало с TypeError
        if (!$query) return 0;
        $sent = 0;
        while ($t = $this->db->fetch_array($query)) {
            $subj = $subject;
            $text = $message;

            // Шаблон не правили: берём его на языке получателя
            if ($useDefault) {
                $ls   = get_lang_section('mass_reseed', (string)($t['language'] ?? ''));
                $subj = (string)($ls['msg_subject'] ?? $subject);
                $text = (string)($ls['msg_body'] ?? $message);
            }

            $url = $this->base() . '/' . get_torrent_link((int)$t['id']);
            // Без htmlspecialchars: ЛС проходит через BBCode-парсер, который сам экранирует.
            // Раньше ники и названия с & или кавычками приходили как «&amp;», «&quot;»
            $body = str_replace(
                ['{username}', '{torrentname}'],
                [(string)$t['username'], '[url=' . $url . ']' . str_replace(['[', ']'], ['(', ')'], (string)$t['name']) . '[/url]'],
                $text
            );
            if (send_pm(['subject' => $subj, 'message' => $body, 'touid' => (int)$t['owner']], $senderId, true)) {
                $sent++;
            }
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
        global $_this_script_, $lang;
        $raw = $_POST['torrents'] ?? [];
        $ids = $this->validateTorrentIds($raw);
        if (!$ids) {
            // Раньше ошибка печаталась голым блоком ДО шапки страницы
            flash_message($lang->mass_reseed['flash_none_selected'], 'error');
            admin_redirect($_this_script_);
            exit;
        }
        $truncated = is_array($raw) && count(array_unique(array_filter(array_map('intval', $raw)))) > count($ids);

        stdhead($lang->mass_reseed['title_form']);
        $this->assets();
        $this->renderReseedForm($ids, $truncated);
        stdfoot();
        exit;
    }

    private function showWeakTorrents(): void
    {
        global $lang;
        stdhead($lang->mass_reseed['title_list']);
        $this->assets();
        $this->renderWeakTorrentsTable();
        stdfoot();
    }

    private function head(string $icon, string $cls, string $title, string $sub, string $right = ''): void
    {
        echo '<div class="rs-card mb-3"><div class="rs-head"><span class="rs-head-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
           . '<div style="min-width:0"><h1 class="rs-title">' . $this->e($title) . '</h1><div class="rs-sub">' . $this->e($sub) . '</div></div>'
           . ($right !== '' ? '<div class="ms-auto d-flex flex-wrap gap-2 align-items-center">' . $right . '</div>' : '')
           . '</div></div>';
    }

    private function renderReseedForm(array $ids, bool $truncated): void
    {
        global $mybb, $_this_script_, $lang;
        $L = $lang->mass_reseed;
        $ph = $this->idPlaceholders($ids);

        // Сколько сообщений уйдёт в каждом режиме — чтобы не разослать случайно сотни ЛС
        $q = $this->db->sql_query_prepared("SELECT COUNT(*) AS n FROM torrents t INNER JOIN users u ON t.owner = u.id WHERE t.id IN ({$ph}) AND t.seeders = 0 AND t.owner > 0 AND u.enabled = 'yes'", array_values($ids));
        $nOwners = (int)(($q ? $this->db->fetch_array($q) : [])['n'] ?? 0);
        $q = $this->db->sql_query_prepared("SELECT COUNT(DISTINCT s.userid, s.torrentid) AS n FROM snatched s INNER JOIN torrents t ON s.torrentid = t.id INNER JOIN users u ON s.userid = u.id WHERE s.finished = 'yes' AND s.torrentid IN ({$ph}) AND s.userid > 0 AND t.seeders = 0 AND u.enabled = 'yes'", array_values($ids));
        $nSnatch = (int)(($q ? $this->db->fetch_array($q) : [])['n'] ?? 0);

        $list = [];
        $q = $this->db->sql_query_prepared("SELECT id, name, times_completed, leechers FROM torrents WHERE id IN ({$ph}) ORDER BY name", array_values($ids));
        while ($q && ($r = $this->db->fetch_array($q))) $list[] = $r;

        $subject = $L['msg_subject'];
        $body    = $L['msg_body'];
        $n = count($ids);
        ?>
<div class="container mt-3 mb-4 rs">
    <?php $this->head('fa-paper-plane', 'ic-green', $L['pane_form'], $L['pane_form_sub'],
        '<a href="' . $this->e($_this_script_) . '" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>' . $this->e($L['btn_back']) . '</a>'); ?>

    <?php if ($truncated): ?>
    <div class="alert alert-warning d-flex gap-2 rounded-4"><i class="fa-solid fa-triangle-exclamation mt-1"></i><div><?= $this->e(ags_fmt($L['hint_truncated'], self::MAX_TORRENTS_PER_REQUEST)) ?></div></div>
    <?php endif; ?>

    <form method="post" action="<?= $this->e($_this_script_) ?>" id="reseedForm" novalidate>
        <input type="hidden" name="my_post_key" value="<?= $this->e($mybb->post_code) ?>">
        <input type="hidden" name="do" value="request_reseed_final">
        <input type="hidden" name="torrents" value="<?= $this->e(implode(',', $ids)) ?>">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="rs-card h-100">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-blue"><i class="fa-solid fa-envelope"></i></span>
                        <div><h2 class="rs-sec-title"><?= $this->e($L['sec_message']) ?></h2><div class="rs-muted"><?= $this->e($L['sec_message_sub']) ?></div></div></div>
                    <div class="p-3 p-md-4">
                        <label class="form-label" for="rsSubject"><i class="fa-solid fa-heading"></i><?= $this->e($L['lbl_subject']) ?></label>
                        <input type="text" id="rsSubject" name="subject" class="form-control mb-3" value="<?= $this->e($subject) ?>" required maxlength="120">
                        <label class="form-label" for="rsMessage"><i class="fa-solid fa-align-left"></i><?= $this->e($L['lbl_text']) ?></label>
                        <textarea id="rsMessage" name="message" class="form-control" rows="9" required><?= $this->e($body) ?></textarea>
                        <div class="rs-tags mt-2">
                            <span class="rs-muted me-1"><?= $this->e($L['lbl_insert']) ?></span>
                            <button type="button" class="rs-tag" data-ins="{username}"><i class="fa-solid fa-user"></i>{username}</button>
                            <button type="button" class="rs-tag" data-ins="{torrentname}"><i class="fa-solid fa-link"></i>{torrentname}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="rs-card mb-3">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-amber"><i class="fa-solid fa-users"></i></span><h2 class="rs-sec-title"><?= $this->e($L['sec_recipients']) ?></h2></div>
                    <div class="p-3">
                        <label class="rs-opt mb-2"><input type="radio" name="requestfrom" value="owner" checked data-n="<?= $nOwners ?>">
                            <span class="rs-sec-icon ic-blue"><i class="fa-solid fa-user-pen"></i></span>
                            <span class="flex-grow-1"><b class="d-block"><?= $this->e($L['opt_owner']) ?></b><small class="rs-muted"><?= $this->e(ags_fmt($L['lbl_msg_count'], number_format($nOwners))) ?></small></span></label>
                        <label class="rs-opt"><input type="radio" name="requestfrom" value="all" data-n="<?= $nSnatch ?>">
                            <span class="rs-sec-icon ic-purple"><i class="fa-solid fa-users"></i></span>
                            <span class="flex-grow-1"><b class="d-block"><?= $this->e($L['opt_all']) ?></b><small class="rs-muted"><?= $this->e(ags_fmt($L['lbl_msg_count'], number_format($nSnatch))) ?></small></span></label>
                    </div>
                </div>
                <div class="rs-card mb-3">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-slate"><i class="fa-solid fa-sliders"></i></span><h2 class="rs-sec-title"><?= $this->e($L['sec_options']) ?></h2></div>
                    <div class="p-3">
                        <label class="form-label" for="rsSender"><i class="fa-solid fa-user-tie"></i><?= $this->e($L['lbl_send_as']) ?></label>
                        <select class="form-select mb-3" name="sender" id="rsSender">
                            <option value="0"><?= $this->e($L['opt_system']) ?></option>
                            <option value="<?= (int)($this->curUser['id'] ?? 0) ?>"><?= $this->e($this->curUser['username'] ?? $L['opt_me']) ?></option>
                        </select>
                        <label class="rs-opt" for="rsDouble">
                            <span class="rs-sec-icon ic-amber"><i class="fa-solid fa-angles-up"></i></span>
                            <span class="flex-grow-1"><b class="d-block"><?= $this->e($L['lbl_double']) ?></b><small class="rs-muted"><?= $this->e($L['hint_double']) ?></small></span>
                            <input type="hidden" name="doubleupload" value="no">
                            <input class="form-check-input m-0" type="checkbox" role="switch" id="rsDouble" name="doubleupload" value="yes" style="width:2.5em;height:1.35em">
                        </label>
                    </div>
                </div>
                <div class="rs-card">
                    <div class="rs-sec-head"><span class="rs-sec-icon ic-red"><i class="fa-solid fa-magnet"></i></span>
                        <h2 class="rs-sec-title"><?= $this->e(ags_fmt($n === 1 ? $L['sec_torrents_one'] : $L['sec_torrents_many'], $n)) ?></h2></div>
                    <ul class="rs-mini">
                        <?php foreach ($list as $t): ?>
                        <li><i class="fa-solid fa-magnet text-body-secondary"></i><span class="text-truncate"><?= $this->e($t['name']) ?></span><span class="rs-muted ms-auto text-nowrap"><i class="fa-solid fa-circle-check me-1"></i><?= (int)$t['times_completed'] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="rs-card rs-bar">
            <span class="rs-muted"><i class="fa-solid fa-envelope me-1"></i><?= ags_fmt($this->e($L['lbl_will_send']), '<b id="rsCount">' . number_format($nOwners) . '</b>') ?></span>
            <button type="submit" class="btn btn-success px-4" id="rsSend"><i class="fa-solid fa-paper-plane me-1"></i><?= $this->e($L['btn_send']) ?></button>
        </div>
    </form>
</div>

<link rel="stylesheet" href="<?= $this->e($this->base()) ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $this->e($this->base()) ?>/scripts/sweetalert2.min.js"></script>
<?php $this->jsLang(); ?>
<script>
(function () {
    'use strict';
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (k, fb, ...a) => {
        let s = typeof L[k] === 'string' ? L[k] : fb;
        a.forEach((v, i) => { s = s.split('{' + (i + 1) + '}').join(String(v)).split('%' + (i + 1) + '$s').join(String(v)); });
        return s;
    };
    const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    const icon = cls => { const i = document.createElement('i'); i.className = cls; return i; };

    const $ = id => document.getElementById(id);
    const form = $('reseedForm'), msg = $('rsMessage');
    const TORRENTS = <?= $n ?>;
    const note = '\n\n' + t('double_note', 'Bonus: you get double upload credit for seeding this torrent again!');
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
        if (count === 0) {
            window.Swal
                ? Swal.fire({ titleText: t('nobody_title', 'Nobody to notify'), text: t('nobody_text', 'Try “Everyone who snatched”.'), icon: 'info' })
                : alert(t('nobody_title', 'Nobody to notify'));
            return;
        }
        let ok;
        if (window.Swal) {
            const box = document.createElement('div');
            box.className = 'text-start small text-body-secondary';
            box.append(icon('fa-solid fa-magnet me-1'), t('confirm_torrents', '{1} torrent(s)', TORRENTS.toLocaleString()));
            if (dbl) box.append(document.createElement('br'), icon('fa-solid fa-angles-up me-1 text-warning'), t('confirm_double', 'Double upload will be enabled'));
            ok = (await Swal.fire({
                titleText: t('confirm_send', 'Send {1} message(s)?', count.toLocaleString()), icon: 'question',
                html: box,
                showCancelButton: true, reverseButtons: true,
                confirmButtonText: '<i class="fa-solid fa-paper-plane me-1"></i>' + esc(t('btn_send', 'Send')),
                cancelButtonText: esc(t('btn_cancel', 'Cancel')),
                confirmButtonColor: '#16a34a', cancelButtonColor: '#6c757d',
            })).isConfirmed;
        } else ok = confirm(t('confirm_send', 'Send {1} message(s)?', count));
        if (!ok) return;
        const spin = document.createElement('span'); spin.className = 'spinner-border spinner-border-sm me-1';
        $('rsSend').disabled = true; $('rsSend').replaceChildren(spin, document.createTextNode(t('sending', 'Sending…')));
        if (window.Swal) Swal.fire({ titleText: t('sending', 'Sending…'), allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        HTMLFormElement.prototype.submit.call(form);
    });
})();
</script>
        <?php
    }

    // ═══ Список «слабых» торрентов ══════════════════════════════════
    private function renderWeakTorrentsTable(): void
    {
        global $_this_script_, $lang;
        $L = $lang->mass_reseed;

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
    <?php $this->head('fa-seedling', 'ic-green', $L['pane_list'], $L['pane_list_sub'],
        '<span class="rs-ver"><i class="fa-solid fa-code-branch me-1"></i>' . MR_VERSION . '</span>'); ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-skull',          'ic-red',    $L['kpi_dead'],        number_format((int)($st['n'] ?? 0))],
            ['fa-hourglass-half', 'ic-amber',  $L['kpi_waiting'],     number_format((int)($st['waiting'] ?? 0))],
            ['fa-circle-check',   'ic-blue',   $L['kpi_snatched'],    number_format((int)($st['snatched'] ?? 0))],
            ['fa-layer-group',    'ic-purple', $L['kpi_per_request'], ags_fmt($L['kpi_up_to'], self::MAX_TORRENTS_PER_REQUEST)],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="rs-card rs-kpi"><span class="rs-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="rs-kpi-label"><?= $this->e($label) ?></div><div class="rs-kpi-value"><?= $this->e($val) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= $this->e($self) ?>" id="torrentSelectionForm">
        <input type="hidden" name="do" value="request_reseed">
        <div class="rs-card overflow-hidden">
            <div class="rs-toolbar">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="rs-muted me-1"><i class="fa-solid fa-arrow-down-wide-short me-1"></i><?= $this->e($L['lbl_sort']) ?></span>
                    <?php foreach (['snatched' => ['fa-circle-check', $L['opt_sort_snatched']], 'leechers' => ['fa-hourglass-half', $L['opt_sort_leechers']], 'added' => ['fa-calendar', $L['opt_sort_added']], 'name' => ['fa-font', $L['opt_sort_name']]] as $k => [$ic, $lbl]): ?>
                    <a href="<?= $sortUrl($k) ?>" class="rs-sort<?= $sort === $k ? ' active' : '' ?>"><i class="fa-solid <?= $ic ?>"></i><?= $this->e($lbl) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="position-relative rs-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" id="rsFilter" placeholder="<?= $this->e($L['ph_filter']) ?>"></div>
            </div>
<?php if (!$rows): ?>
            <div class="rs-empty"><i class="fa-solid fa-circle-check text-success"></i><div class="fw-semibold"><?= $this->e($L['empty_title']) ?></div><div class="small"><?= $this->e($L['empty_text']) ?></div></div>
<?php else: ?>
            <div class="table-responsive"><table class="rs-table">
                <thead><tr>
                    <th style="width:40px"><input class="form-check-input" type="checkbox" id="selectAllTorrents" aria-label="<?= $this->e($L['aria_select_all']) ?>"></th>
                    <th><i class="fa-solid fa-magnet"></i><?= $this->e($L['col_torrent']) ?></th>
                    <th><i class="fa-solid fa-user"></i><?= $this->e($L['col_uploader']) ?></th>
                    <th class="text-center"><i class="fa-solid fa-hourglass-half"></i><?= $this->e($L['col_leech']) ?></th>
                    <th class="text-center"><i class="fa-solid fa-circle-check"></i><?= $this->e($L['col_snatched']) ?></th>
                    <th class="text-end"></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $t):
                    $tid = (int)$t['id']; $le = (int)$t['leechers']; $sn = (int)$t['times_completed']; ?>
                    <tr data-name="<?= $this->e(mb_strtolower((string)$t['name'])) ?>" class="<?= $le > 0 ? 'is-wait' : '' ?>">
                        <td><input class="form-check-input torrent-checkbox" type="checkbox" name="torrents[]" value="<?= $tid ?>" aria-label="<?= $this->e($L['aria_select']) ?>"></td>
                        <td>
                            <a href="<?= $this->e($base . '/' . get_torrent_link($tid)) ?>" class="rs-tname" target="_blank"><?= $this->e($t['name']) ?></a>
                            <div class="rs-muted"><i class="fa-solid fa-hard-drive me-1"></i><?= mksize((float)($t['size'] ?? 0)) ?> · <i class="fa-solid fa-calendar ms-1 me-1"></i><?= my_datee('relative', (int)$t['added']) ?></div>
                        </td>
                        <td><?= $t['username'] !== null
                            ? '<a href="' . $this->e($base . '/' . get_profile_link((int)$t['owner'])) . '" class="text-decoration-none">' . format_name($this->e($t['username']), (int)$t['usergroup']) . '</a>'
                            : '<em class="text-body-secondary">' . $this->e($L['lbl_deleted']) . '</em>' ?></td>
                        <td class="text-center"><span class="rs-pill <?= $le > 0 ? 't-amber' : 't-slate' ?>"><?= number_format($le) ?></span></td>
                        <td class="text-center"><a href="<?= $this->e($base . '/viewsnatches.php?id=' . $tid) ?>" class="rs-pill <?= $sn > 0 ? 't-blue' : 't-slate' ?> text-decoration-none" title="<?= $this->e($L['tip_snatches']) ?>"><?= number_format($sn) ?></a></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= $this->e($base . '/upload.php?id=' . $tid) ?>" class="rs-act" title="<?= $this->e($L['tip_edit']) ?>"><i class="fa-solid fa-pen"></i></a>
                            <a href="<?= $this->e($base . '/admin/index.php?act=fastdelete&id=' . $tid) ?>" class="rs-act danger rs-del" title="<?= $this->e($L['tip_delete']) ?>" data-name="<?= $this->e($t['name']) ?>"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if (count($rows) >= self::LIST_LIMIT): ?><div class="rs-muted text-center py-2 border-top"><?= $this->e(ags_fmt($L['hint_list_limit'], self::LIST_LIMIT)) ?></div><?php endif; ?>
<?php endif; ?>
        </div>

        <?php if ($rows): ?>
        <div class="rs-card rs-bar">
            <span class="rs-muted"><i class="fa-solid fa-square-check me-1"></i><?= ags_fmt($this->e($L['lbl_selected']), '<b id="selectedCount">0</b>') ?> <span class="ms-1"><?= $this->e(ags_fmt($L['lbl_max'], self::MAX_TORRENTS_PER_REQUEST)) ?></span></span>
            <button type="submit" class="btn btn-success px-4" id="submitButton" disabled><i class="fa-solid fa-seedling me-1"></i><?= $this->e($L['btn_request']) ?></button>
        </div>
        <?php endif; ?>
    </form>
</div>

<link rel="stylesheet" href="<?= $this->e($base) ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $this->e($base) ?>/scripts/sweetalert2.min.js"></script>
<?php $this->jsLang(); ?>
<script>
(function () {
    'use strict';
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (k, fb, ...a) => {
        let s = typeof L[k] === 'string' ? L[k] : fb;
        a.forEach((v, i) => { s = s.split('{' + (i + 1) + '}').join(String(v)).split('%' + (i + 1) + '$s').join(String(v)); });
        return s;
    };
    const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

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
            ? (await Swal.fire({ titleText: t('delete_title', 'Delete torrent?'), text: a.dataset.name, icon: 'warning', showCancelButton: true, reverseButtons: true, focusCancel: true,
                confirmButtonText: esc(t('delete_btn', 'Delete')), cancelButtonText: esc(t('btn_cancel', 'Cancel')), confirmButtonColor: '#dc2626', cancelButtonColor: '#6c757d' })).isConfirmed
            : confirm(t('delete_confirm', 'Delete torrent {1}?', a.dataset.name));
        if (go) location.href = a.href;
    }));
    $('torrentSelectionForm')?.addEventListener('submit', e => {
        const n = [...document.querySelectorAll('.torrent-checkbox')].filter(b => b.checked).length;
        if (n === 0) { e.preventDefault(); return; }
        if (n > MAX && window.Swal) {
            e.preventDefault();
            Swal.fire({ titleText: t('too_many_title', 'Too many selected'), text: t('too_many_text', 'Only the first {1} will be included. Continue?', MAX), icon: 'info', showCancelButton: true,
                confirmButtonText: esc(t('continue', 'Continue')), cancelButtonText: esc(t('btn_cancel', 'Cancel')) })
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