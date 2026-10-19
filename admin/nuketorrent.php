<?php

declare(strict_types=1);

require_once __DIR__ . '/../global.php';

define('NT_VERSION', '0.4');
define('NT_ASSET_VER', 1);

// Только персонал с доступом к панели (как в admin/index.php)
if (!is_mod($usergroups) || (int)($usergroups['canstaffpanel'] ?? 0) !== 1) {
    print_no_permission(true);
}



$lang->load('nuketorrent');
$L = $lang->nuketorrent;

$do = $_POST['do'] ?? $_GET['do'] ?? '';
$do = is_string($do) ? $do : '';

// Сначала проверяем сырое значение (мусор вроде "5abc" или id[]=5 — в лог), потом приводим к числу
$rawId = $_POST['id'] ?? $_GET['id'] ?? null;
int_check($rawId);
$id = (int)$rawId;

$query   = $db->sql_query_prepared('SELECT id, name, isnuked, WhyNuked FROM torrents WHERE id = ?', [$id]);
$torrent = $query ? $db->fetch_array($query) : null;
if (!$torrent) {
    stderr($L['err_title'] ?? 'Error', $L['err_invalid_torrent'] ?? 'Invalid torrent!');
}

// ── Форма ───────────────────────────────────────────────────────────────────
if ($do === '') {
    $autoreasons = [
        ['fa-image',             $L['opt_bad_quality']    ?? 'Bad Quality'],
        ['fa-compact-disc',      $L['opt_bad_rip']        ?? 'Bad Rip'],
        ['fa-clone',             $L['opt_duplicate']      ?? 'Duplicate Torrent'],
        ['fa-mask',              $L['opt_fake']           ?? 'Fake Content'],
        ['fa-folder-tree',       $L['opt_wrong_category'] ?? 'Wrong Category'],
        ['fa-file-circle-xmark', $L['opt_broken_files']   ?? 'Missing / Broken Files'],
    ];

    $chips = '';
    foreach ($autoreasons as [$icon, $reason]) {
        $r = htmlspecialchars_uni($reason);
        $chips .= '<button type="button" class="nt-chip" data-reason="' . $r . '"><i class="fa-solid ' . $icon . '"></i>' . $r . '</button>';
    }

    $isNuked   = ($torrent['isnuked'] ?? 'no') === 'yes';
    $nameH     = htmlspecialchars_uni((string)$torrent['name']);
    $postKey   = htmlspecialchars_uni((string)$mybb->post_code);
    $action    = htmlspecialchars_uni($_SERVER['SCRIPT_NAME']);
    $backUrl   = htmlspecialchars_uni($BASEURL . '/' . get_torrent_link($id));
    // WhyNuked хранится уже экранированным
    $curReason = (string)($torrent['WhyNuked'] ?? '');

    $h = static fn(string $key, string $fallback): string => htmlspecialchars_uni($L[$key] ?? $fallback);

    // js_* ключи без префикса — для admin/scripts/nuketorrent.js
    $jsLang = [];
    foreach ((array)$L as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $jsLang[substr((string)$k, 3)] = (string)$v;
        }
    }

    stdhead(sprintf($L['page_title'] ?? 'Nuke Torrent: %1$s', (string)$id));

    echo '
<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/nuketorrent.css?ver=' . NT_ASSET_VER . '">
<script>const AGS_LANG = ' . json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>
<script src="' . $BASEURL . '/scripts/sweetalert2.min.js" defer></script>
<script src="' . $BASEURL . '/admin/scripts/nuketorrent.js?ver=' . NT_ASSET_VER . '" defer></script>

<div class="nt-wrap">
  <div class="nt-card">
    <div class="nt-head">
      <span class="nt-ico"><i class="fa-solid fa-radiation"></i></span>
      <div class="flex-grow-1" style="min-width:0">
        <h1 class="nt-title">' . ($isNuked ? $h('sec_nuked', 'Torrent is nuked') : $h('sec_nuke', 'Nuke Torrent')) . '</h1>
        <p class="nt-sub">' . $nameH . '<span class="nt-id">#' . $id . '</span></p>
      </div>
    </div>

    <form method="post" action="' . $action . '" id="ntForm">
      <input type="hidden" name="act" value="nuketorrent">
      <input type="hidden" name="do" value="nuke_torrent">
      <input type="hidden" name="id" value="' . $id . '">
      <input type="hidden" name="my_post_key" value="' . $postKey . '">

      <div class="nt-body">'
      . ($isNuked ? '
        <div class="nt-current">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <div><div class="fw-semibold">' . $h('lbl_current_reason', 'Current reason') . '</div>
          <div class="small">' . ($curReason !== '' ? $curReason : '<em>' . $h('lbl_no_reason', 'no reason given') . '</em>') . '</div>
          <div class="small text-body-secondary mt-1">' . $h('hint_replace_reason', 'Saving a new reason replaces it.') . '</div></div>
        </div>' : '') . '

        <div class="nt-label"><i class="fa-solid fa-bolt text-danger"></i>' . $h('lbl_quick_reasons', 'Quick reasons') . '</div>
        <div class="nt-chips">' . $chips . '</div>

        <label class="nt-label" for="ntReason"><i class="fa-solid fa-pen text-secondary"></i>' . $h('lbl_reason', 'Reason') . '</label>
        <textarea class="form-control nt-textarea" name="WhyNuked" id="ntReason" maxlength="255" required
                  placeholder="' . $h('ph_reason', 'Explain why this torrent is being nuked (min. 6 characters)') . '"></textarea>
        <div class="nt-meta">
          <span id="ntHint">' . $h('hint_min_chars', 'Min. 6 characters') . '</span>
          <span id="ntCount">0 / 255</span>
        </div>
      </div>

      <div class="nt-foot">
        <a href="' . $backUrl . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>' . $h('btn_back', 'Back to torrent') . '</a>
        <div class="nt-foot-right">'
        . ($isNuked ? '
          <button type="button" id="ntUnnukeBtn" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>' . $h('btn_unnuke', 'Unnuke') . '</button>' : '') . '
          <button type="submit" class="btn btn-danger btn-sm" id="ntSubmit" disabled><i class="fa-solid fa-radiation me-1"></i>'
            . ($isNuked ? $h('btn_update', 'Update reason') : $h('btn_nuke', 'Nuke torrent')) . '</button>
        </div>
      </div>
    </form>'
    . ($isNuked ? '
    <form method="post" action="' . $action . '" id="ntUnnukeForm">
      <input type="hidden" name="act" value="nuketorrent">
      <input type="hidden" name="do" value="unnuke_torrent">
      <input type="hidden" name="id" value="' . $id . '">
      <input type="hidden" name="my_post_key" value="' . $postKey . '">
    </form>' : '') . '
  </div>
</div>';

    stdfoot();
    return 1;
}

// ── Изменяющие действия — только POST с валидным CSRF-токеном ──────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'), true)) {
    http_response_code(403);
    stderr($L['err_title'] ?? 'Error', $L['err_csrf'] ?? 'Invalid security token or request method. Please use the form.');
}

if ($do === 'nuke_torrent') {
    $reasonRaw = trim(is_string($_POST['WhyNuked'] ?? null) ? $_POST['WhyNuked'] : '');

    if (mb_strlen($reasonRaw) < 6) {
        stderr($L['err_title'] ?? 'Error', $L['err_reason_short'] ?? 'Reason too short! Min. 6 characters.');
    }

    // Хранится экранированным, как и раньше (вывод WhyNuked в других местах может на это рассчитывать)
    $reason = htmlspecialchars_uni(mb_substr($reasonRaw, 0, 255));

    $db->sql_query_prepared('UPDATE torrents SET isnuked = \'yes\', WhyNuked = ? WHERE id = ?', [$reason, $id]);
    write_log('Torrent #' . $id . ' (' . htmlspecialchars_uni((string)$torrent['name']) . ') was nuked by '
        . htmlspecialchars_uni((string)$CURUSER['username']) . ': ' . $reason);

    redirect('details.php?id=' . $id, $L['flash_nuked'] ?? 'The torrent has been nuked!');
    return 1;
}

if ($do === 'unnuke_torrent') {
    $db->sql_query_prepared('UPDATE torrents SET isnuked = \'no\', WhyNuked = \'\' WHERE id = ?', [$id]);
    write_log('Torrent #' . $id . ' (' . htmlspecialchars_uni((string)$torrent['name']) . ') was unnuked by '
        . htmlspecialchars_uni((string)$CURUSER['username']));

    redirect('details.php?id=' . $id, $L['flash_unnuked'] ?? 'The torrent has been unnuked!');
    return 1;
}

print_no_permission(true);