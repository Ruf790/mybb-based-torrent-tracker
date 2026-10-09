<?php
declare(strict_types=1);

// Access check
if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

$lang->load('passkeysearch');

define('PS_VERSION', 'v0.2');

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… в строку из ланга. $lang->load() превращает {N}
     * в %N$s, поэтому заменяем оба формата.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach (array_values($args) as $i => $v) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$v;
            $map['%' . $n . '$s'] = (string)$v;
            $map['%' . $n . '$d'] = (string)$v;
        }
        return strtr($str, $map);
    }
}

$do      = (string)($_POST['do'] ?? $_GET['do'] ?? '0');
$passkey = '';
$notice  = null;   // ['type' => success|warning|danger, 'text' => ..., 'icon' => ...]
$found   = null;

/**
 * Достаём passkey из того, что вставили: сам ключ, announce-URL
 * (…/announce.php?passkey=…) или ссылку на .torrent — раньше принимался
 * только «голый» ключ из 32 символов.
 */
function ps_extract_passkey(string $raw): string
{
    $raw = strtolower(trim($raw));
    if (preg_match('/passkey=([a-f0-9]{32})/', $raw, $m)) return $m[1];
    if (preg_match('/\b([a-f0-9]{32})\b/', $raw, $m))      return $m[1];
    return $raw;
}

// ── Действия ─────────────────────────────────────────────────────────
if ($do === '2') {
    // Сброс — только POST + CSRF
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $notice = ['danger', $lang->passkeysearch['flash_bad_method'], 'fa-ban'];
    } elseif (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        $notice = ['danger', $lang->passkeysearch['flash_csrf'], 'fa-shield-halved'];
    } else {
        $passkey = ps_extract_passkey((string)($_POST['passkey'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $passkey)) {
            $notice = ['danger', $lang->passkeysearch['flash_reset_invalid'], 'fa-circle-xmark'];
        } else {
            $uq   = $db->sql_query_prepared("SELECT id, username FROM users WHERE passkey = ? LIMIT 1", [$passkey]);
            $user = $uq ? $db->fetch_array($uq) : null;
            $db->sql_query_prepared("UPDATE users SET passkey = '' WHERE passkey = ?", [$passkey]);
            if ($db->affected_rows() > 0) {
                write_log('Passkey of ' . ($user['username'] ?? 'unknown') . ' reset by ' . ($CURUSER['username'] ?? 'staff') . ' (' . substr($passkey, 0, 8) . '…)', 'security', 1);
                $notice = ['success', ags_fmt($lang->passkeysearch['flash_reset_ok'], htmlspecialchars((string)($user['username'] ?? $lang->passkeysearch['txt_unknown_user']))), 'fa-circle-check'];
                $passkey = '';
            } else {
                $notice = ['warning', $lang->passkeysearch['flash_reset_none'], 'fa-user-slash'];
            }
        }
    }
} elseif ($do === '1') {
    $passkey = ps_extract_passkey((string)($_POST['passkey'] ?? $_GET['passkey'] ?? ''));
    if ($passkey === '') {
        $notice = ['danger', $lang->passkeysearch['flash_empty'], 'fa-keyboard'];
    } elseif (!preg_match('/^[a-f0-9]{32}$/', $passkey)) {
        $notice = ['danger', $lang->passkeysearch['flash_invalid'], 'fa-circle-xmark'];
    } else {
        $q = $db->sql_query_prepared('SELECT u.*, g.title AS gtitle, g.image AS gimage FROM users u LEFT JOIN usergroups g ON (u.usergroup = g.gid) WHERE u.passkey = ?', [$passkey]);
        $found = ($q && $db->num_rows($q) > 0) ? $db->fetch_array($q) : null;
        if (!$found) {
            $notice = ['warning', $lang->passkeysearch['flash_not_found'], 'fa-user-slash'];
        }
    }
}

// Строки для JS: ключи js_* без префикса
$psJsLang = [];
foreach ($lang->passkeysearch as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $psJsLang[substr((string)$k, 3)] = (string)$v;
    }
}

stdhead($lang->passkeysearch['title']);
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css" type="text/css" media="screen" />';
ps_styles();

$self = htmlspecialchars((string)($_this_script_ ?? $_SERVER['SCRIPT_NAME']));
$key  = htmlspecialchars((string)$mybb->post_code, ENT_QUOTES);
$L    = static fn(string $k): string => htmlspecialchars($lang->passkeysearch[$k]);
?>
<script>
const AGS_LANG = <?= json_encode($psJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
// Перевод с английским fallback; {1} и %1$s (так $lang->load() переписывает плейсхолдеры)
function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((a, i) => { s = s.split('{' + (i + 1) + '}').join(String(a)).split('%' + (i + 1) + '$s').join(String(a)); });
    return s;
}
// Иконка + текст без innerHTML
function psIconText(el, icon, text) {
    const i = document.createElement('i');
    i.className = icon;
    el.replaceChildren(i, document.createTextNode(text));
}
</script>
<div class="container mt-3 mb-4 ps">

    <div class="ps-card mb-3"><div class="ps-head">
        <span class="ps-head-icon"><i class="fa-solid fa-fingerprint"></i></span>
        <div style="min-width:0">
            <h1 class="ps-title"><?= $L('title') ?></h1>
            <div class="ps-sub"><?= $L('subtitle') ?></div>
        </div>
        <span class="ps-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i><?= PS_VERSION ?></span>
    </div></div>

    <form method="post" action="<?= $self ?>" class="ps-card p-3 mb-3" id="passkeyForm">
        <input type="hidden" name="act" value="passkeysearch">
        <input type="hidden" name="do" value="1">
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
            <input type="text" id="passkeyInput" name="passkey" class="form-control font-monospace"
                   placeholder="<?= $L('ph_passkey') ?>" value="<?= htmlspecialchars($passkey) ?>" required autocomplete="off" spellcheck="false">
            <button class="btn btn-outline-secondary" type="button" id="clearForm" title="<?= $L('btn_clear') ?>" aria-label="<?= $L('btn_clear') ?>"><i class="fa-solid fa-xmark"></i></button>
            <button class="btn btn-primary px-4" type="submit" id="psGo"><i class="fa-solid fa-magnifying-glass me-1"></i><?= $L('btn_search') ?></button>
        </div>
        <div class="ps-hint mt-2" id="psHint"><i class="fa-solid fa-circle-info me-1"></i><?= $L('hint_format') ?></div>
    </form>

<?php if ($notice): [$ntype, $ntext, $nicon] = $notice; ?>
    <div class="ps-notice is-<?= $ntype ?> mb-3"><i class="fa-solid <?= $nicon ?>"></i><div><?= $ntext ?></div></div>
<?php endif; ?>

<?php if ($found):
    include_once INC_PATH . '/functions_ratio.php';
    $u        = $found;
    $uid      = (int)$u['id'];
    $name     = (string)$u['username'];
    $up       = (float)($u['uploaded'] ?? 0);
    $down     = (float)($u['downloaded'] ?? 0);
    $ratio    = get_user_ratio($up, $down);
    $rnum     = (float)str_replace(['∞', '---', ','], ['999', '0', ''], strip_tags((string)$ratio));
    $rcls     = $rnum >= 2 ? 'is-good' : ($rnum >= 1 ? 'is-ok' : ($rnum >= .5 ? 'is-warn' : 'is-bad'));
    $last     = (int)($u['lastactive'] ?? 0);
    $online   = $last > 0 && TIMENOW - $last < 300;
    // format_name получал ник без экранирования
    $nameHtml = function_exists('format_name') ? format_name(htmlspecialchars($name), (int)$u['usergroup']) : htmlspecialchars($name);
    $profile  = $BASEURL . '/' . (function_exists('get_profile_link') ? get_profile_link($uid) : 'userdetails.php?id=' . $uid);

    $av = function_exists('format_avatar') ? format_avatar($u['avatar'] ?? '', $u['avatardimensions'] ?? '') : [];
    $avatar = (!empty($av['image']) && empty($av['is_placeholder']) && !str_starts_with((string)$av['image'], '<'))
        ? '<img src="' . htmlspecialchars((string)$av['image']) . '" alt="">'
        : htmlspecialchars(mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1)));

    // Активные раздачи с этим аккаунтом (для оценки, что сломает сброс)
    $seed = $leech = 0;
    $pq = $db->sql_query_prepared("SELECT seeder, COUNT(*) AS n FROM peers WHERE userid = ? GROUP BY seeder", [$uid]);
    while ($pq && ($p = $db->fetch_array($pq))) {
        if ($p['seeder'] === 'yes') $seed = (int)$p['n']; else $leech = (int)$p['n'];
    }
    $never = $L('lbl_never');
    $dt = static fn(int $t): string => $t > 0 ? my_datee('relative', $t) : '<span class="text-body-secondary">' . $never . '</span>';
?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="ps-card h-100 ps-profile">
                <div class="ps-avatar-wrap">
                    <span class="ps-avatar"><?= $avatar ?></span>
                    <span class="ps-dot <?= $online ? 'on' : '' ?>" title="<?= $online ? $L('lbl_online') : $L('lbl_offline') ?>"></span>
                </div>
                <div class="ps-name"><?= $nameHtml ?></div>
                <div class="ps-muted mb-2">
                    <?= htmlspecialchars((string)($u['gtitle'] ?? $lang->passkeysearch['lbl_member'])) ?> · <?= $L('lbl_id') ?> <?= $uid ?>
                    <?php if (!empty($u['gimage']) && str_starts_with(trim((string)$u['gimage']), '<')): ?><span class="ms-1"><?= $u['gimage'] ?></span><?php endif; ?>
                </div>
                <span class="ps-tag <?= $online ? 't-on' : 't-off' ?> mb-3"><i class="fa-solid fa-circle"></i><?= $online ? $L('lbl_online_now') : ags_fmt($L('lbl_last_seen'), strip_tags($dt($last))) ?></span>
                <a href="<?= htmlspecialchars($profile) ?>" class="btn btn-primary btn-sm px-3" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= $L('btn_open_profile') ?></a>
                <div class="ps-rows mt-3">
                    <div class="ps-row"><span><i class="fa-solid fa-envelope"></i><?= $L('lbl_email') ?></span><b title="<?= htmlspecialchars((string)($u['email'] ?? '')) ?>"><?= htmlspecialchars((string)($u['email'] ?? '—')) ?></b></div>
                    <div class="ps-row"><span><i class="fa-solid fa-network-wired"></i><?= $L('lbl_ip') ?></span><b class="font-monospace"><?= htmlspecialchars((string)($u['ipaddress'] ?? '—')) ?></b></div>
                    <div class="ps-row"><span><i class="fa-solid fa-user-plus"></i><?= $L('lbl_joined') ?></span><b><?= $dt((int)($u['added'] ?? 0)) ?></b></div>
                    <div class="ps-row"><span><i class="fa-solid fa-eye"></i><?= $L('lbl_last_active') ?></span><b><?= $dt($last) ?></b></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="ps-card ps-kpi"><span class="ps-kpi-icon ic-green"><i class="fa-solid fa-upload"></i></span><div><div class="ps-kpi-label"><?= $L('kpi_uploaded') ?></div><div class="ps-kpi-value"><?= mksize($up) ?></div></div></div></div>
                <div class="col-6 col-md-3"><div class="ps-card ps-kpi"><span class="ps-kpi-icon ic-red"><i class="fa-solid fa-download"></i></span><div><div class="ps-kpi-label"><?= $L('kpi_downloaded') ?></div><div class="ps-kpi-value"><?= mksize($down) ?></div></div></div></div>
                <div class="col-6 col-md-3"><div class="ps-card ps-kpi"><span class="ps-kpi-icon ic-blue"><i class="fa-solid fa-scale-balanced"></i></span><div><div class="ps-kpi-label"><?= $L('kpi_ratio') ?></div><div class="ps-kpi-value ps-ratio <?= $rcls ?>"><?= $ratio ?></div></div></div></div>
                <div class="col-6 col-md-3"><div class="ps-card ps-kpi"><span class="ps-kpi-icon ic-teal"><i class="fa-solid fa-tower-broadcast"></i></span><div><div class="ps-kpi-label"><?= $L('kpi_active') ?></div><div class="ps-kpi-value"><?= $seed ?> <small class="ps-muted"><?= $L('kpi_seed') ?></small> · <?= $leech ?> <small class="ps-muted"><?= $L('kpi_leech') ?></small></div></div></div></div>
            </div>

            <div class="ps-card">
                <div class="ps-sec-head"><span class="ps-sec-icon ic-purple"><i class="fa-solid fa-key"></i></span>
                    <div><h2 class="ps-sec-title"><?= $L('sec_passkey') ?></h2><div class="ps-muted"><?= $L('sec_passkey_sub') ?></div></div>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="psCopy" data-key="<?= htmlspecialchars($passkey) ?>"><i class="fa-regular fa-copy me-1"></i><?= $L('btn_copy') ?></button></div>
                <div class="p-3">
                    <!-- Раньше формат ключа показывался дважды, а статус «Active & Valid» был зашит -->
                    <div class="ps-key"><?= htmlspecialchars(trim(chunk_split($passkey, 8, ' '))) ?></div>

                    <div class="ps-notice is-warning mt-3 mb-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <div class="fw-bold mb-1"><?= $L('reset_title') ?></div>
                            <ul class="mb-2 ps-3">
                                <li><?= ags_fmt($lang->passkeysearch['reset_li_sessions'], $seed + $leech) ?></li>
                                <li><?= $L('reset_li_redownload') ?></li>
                                <li><?= $L('reset_li_logged') ?></li>
                            </ul>
                            <form method="post" action="<?= $self ?>" id="psResetForm" class="d-inline"
                                  data-name="<?= htmlspecialchars($name) ?>" data-sessions="<?= $seed + $leech ?>">
                                <input type="hidden" name="act" value="passkeysearch">
                                <input type="hidden" name="do" value="2">
                                <input type="hidden" name="passkey" value="<?= htmlspecialchars($passkey) ?>">
                                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                                <button type="submit" class="btn btn-danger btn-sm px-3"><i class="fa-solid fa-rotate me-1"></i><?= $L('btn_reset') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('psResetForm').addEventListener('submit', function (e) {
        // Ник и число сессий — из data-атрибутов (раньше вставлялись в JS-строку через addslashes)
        const msg = t('confirm_reset', 'Reset the passkey of {1}?', this.dataset.name) + '\n\n'
                  + t('confirm_files', '• All their .torrent files stop working') + '\n'
                  + t('confirm_sessions', '• {1} active session(s) will be dropped', this.dataset.sessions);
        if (!confirm(msg)) e.preventDefault();
    });
    document.getElementById('psCopy').addEventListener('click', function () {
        navigator.clipboard?.writeText(this.dataset.key).then(() => {
            psIconText(this, 'fa-solid fa-check me-1', t('copied', 'Copied'));
            setTimeout(() => { psIconText(this, 'fa-regular fa-copy me-1', t('copy', 'Copy')); }, 1500);
        });
    });
    </script>
<?php elseif (!$notice): ?>
    <div class="row g-3">
        <?php foreach ([
            ['fa-user-secret',  'ic-blue',  'intro_who_title',  'intro_who_text'],
            ['fa-link',         'ic-green', 'intro_url_title',  'intro_url_text'],
            ['fa-rotate',       'ic-amber', 'intro_leak_title', 'intro_leak_text'],
        ] as [$ic, $cls, $t, $d]): ?>
        <div class="col-md-4"><div class="ps-card ps-kpi align-items-start"><span class="ps-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="fw-bold mb-1"><?= $L($t) ?></div><div class="ps-muted"><?= $L($d) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<script>
(function () {
    const input = document.getElementById('passkeyInput'), hint = document.getElementById('psHint');
    // Из вставленного URL вытаскиваем ключ прямо в браузере
    function normalize() {
        const v = input.value.trim().toLowerCase();
        const m = v.match(/passkey=([a-f0-9]{32})/) || v.match(/\b([a-f0-9]{32})\b/);
        if (m && m[1] !== v) input.value = m[1];
        const k = input.value.trim().toLowerCase();
        const ok = /^[a-f0-9]{32}$/.test(k);
        input.classList.toggle('is-valid', ok);
        input.classList.toggle('is-invalid', k.length > 0 && !ok && k.length >= 32);
        hint.className = 'ps-hint mt-2' + (ok ? ' ok' : '');
        if (ok) psIconText(hint, 'fa-solid fa-circle-check me-1', t('hint_valid', 'Looks like a valid passkey'));
        else    psIconText(hint, 'fa-solid fa-circle-info me-1', t('hint_format', '32 hexadecimal characters (0-9, a-f)') + (k ? ' · ' + k.length + '/32' : ''));
    }
    input.addEventListener('input', normalize);
    input.addEventListener('paste', () => setTimeout(normalize, 0));
    document.getElementById('clearForm').addEventListener('click', () => { input.value = ''; normalize(); input.focus(); });
    document.getElementById('passkeyForm').addEventListener('submit', e => {
        normalize();
        if (!/^[a-f0-9]{32}$/.test(input.value.trim().toLowerCase())) { e.preventDefault(); input.classList.add('is-invalid'); input.focus(); }
    });
    // Раньше тут была кнопка «сгенерировать случайный ключ» — поиск по случайному ключу ничего не находит
    normalize();
})();
</script>
<?php
stdfoot();

/**
 * Стили страницы. Раньше стили меняли .card (с transform при наведении),
 * .table th и .list-group-item на всей странице, включая шапку сайта.
 */
function ps_styles(): void
{
    echo <<<'HTML'
<style>
.ps { font-size: 1.055rem; }
.ps .ps-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color-translucent); border-radius: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.ps .ps-head { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; }
.ps .ps-head-icon, .ps .ps-kpi-icon, .ps .ps-sec-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ps .ps-head-icon { width: 50px; height: 50px; font-size: 1.4rem; border-radius: .9rem; color: #7c3aed; background: rgba(124,58,237,.12); }
.ps .ps-title { font-size: 1.48rem; font-weight: 700; margin: 0; }
.ps .ps-sub { color: var(--bs-secondary-color); font-size: 1rem; }
.ps .ps-muted { font-size: .9rem; color: var(--bs-secondary-color); }
.ps .ps-ver { font-size: .82rem; font-weight: 700; padding: .25rem .7rem; border-radius: 50rem; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border: 1px solid var(--bs-border-color-translucent); }
.ps .ic-blue   { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.12); }
.ps .ic-green  { color: #16a34a; background: rgba(34,197,94,.12); }
.ps .ic-amber  { color: #d97706; background: rgba(245,158,11,.14); }
.ps .ic-red    { color: #dc2626; background: rgba(239,68,68,.12); }
.ps .ic-purple { color: #7c3aed; background: rgba(124,58,237,.12); }
.ps .ic-teal   { color: #0891b2; background: rgba(8,145,178,.12); }
.ps .btn { border-radius: 50rem; }
.ps .input-group-lg > .form-control { font-size: 1.1rem; }
.ps .input-group > :first-child { border-top-left-radius: 50rem !important; border-bottom-left-radius: 50rem !important; padding-left: 1.1rem; }
.ps .input-group > :last-child { border-top-right-radius: 50rem !important; border-bottom-right-radius: 50rem !important; }
.ps .input-group > .btn:not(:last-child) { border-radius: 0; }
.ps .input-group-text { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); }
.ps .ps-hint { font-size: .88rem; color: var(--bs-secondary-color); }
.ps .ps-hint.ok { color: #16a34a; }

.ps .ps-notice { display: flex; gap: .8rem; padding: .9rem 1.1rem; border-radius: 1rem; border: 1px solid; }
.ps .ps-notice > i { font-size: 1.2rem; margin-top: .15rem; }
.ps .ps-notice.is-success { border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.07); } .ps .ps-notice.is-success > i { color: #16a34a; }
.ps .ps-notice.is-warning { border-color: rgba(245,158,11,.4);  background: rgba(245,158,11,.07); } .ps .ps-notice.is-warning > i { color: #d97706; }
.ps .ps-notice.is-danger  { border-color: rgba(239,68,68,.4);   background: rgba(239,68,68,.06); } .ps .ps-notice.is-danger > i { color: #dc2626; }

.ps .ps-profile { text-align: center; padding: 1.5rem 1.25rem; }
.ps .ps-avatar-wrap { position: relative; display: inline-block; margin-bottom: .75rem; }
.ps .ps-avatar { width: 104px; height: 104px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem; font-weight: 700; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .12); box-shadow: 0 0 0 4px var(--bs-body-bg), 0 0 0 5px var(--bs-border-color-translucent); }
.ps .ps-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ps .ps-dot { position: absolute; right: 6px; bottom: 6px; width: 18px; height: 18px; border-radius: 50%; background: #9ca3af; box-shadow: 0 0 0 3px var(--bs-body-bg); }
.ps .ps-dot.on { background: #22c55e; }
.ps .ps-name { font-size: 1.3rem; font-weight: 700; overflow-wrap: anywhere; }
.ps .ps-tag { display: inline-flex; align-items: center; gap: .35rem; padding: .15rem .65rem; border-radius: 50rem; font-size: .82rem; font-weight: 600; }
.ps .ps-tag i { font-size: .5rem; }
.ps .t-on  { color: #15803d; background: rgba(34,197,94,.1); }
.ps .t-off { color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
.ps .ps-rows { text-align: left; }
.ps .ps-row { display: flex; justify-content: space-between; gap: .75rem; padding: .5rem 0; border-top: 1px dashed var(--bs-border-color-translucent); font-size: .95rem; }
.ps .ps-row span { color: var(--bs-secondary-color); white-space: nowrap; }
.ps .ps-row span i { width: 1.3rem; }
.ps .ps-row b { text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }

.ps .ps-kpi { display: flex; align-items: center; gap: .8rem; padding: .9rem 1.05rem; height: 100%; }
.ps .ps-kpi-icon { width: 44px; height: 44px; border-radius: .8rem; font-size: 1.1rem; }
.ps .ps-kpi-label { font-size: .8rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); }
.ps .ps-kpi-value { font-size: 1.3rem; font-weight: 700; line-height: 1.2; }
.ps .ps-ratio.is-good { color: #16a34a; } .ps .ps-ratio.is-ok { color: var(--bs-primary); } .ps .ps-ratio.is-warn { color: #d97706; } .ps .ps-ratio.is-bad { color: #dc2626; }

.ps .ps-sec-head { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
.ps .ps-sec-icon { width: 40px; height: 40px; border-radius: .75rem; font-size: 1.05rem; }
.ps .ps-sec-title { font-weight: 700; font-size: 1.12rem; margin: 0; }
.ps .ps-key { font-family: var(--bs-font-monospace); font-size: 1.25rem; font-weight: 700; letter-spacing: .06em; padding: .8rem 1rem; border-radius: .85rem; background: var(--bs-tertiary-bg); text-align: center; overflow-wrap: anywhere; }
</style>
HTML;
}