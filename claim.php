<?php
declare(strict_types=1);

/**
 * Claims: my claimed torrents (or a user's / a torrent's claims) + claim / give up.
 * Port of NexusPHP public/claim.php and the addClaim / removeClaim ajax actions.
 */

define('THIS_SCRIPT', 'claim.php');
define('SCRIPTNAME', 'claim.php');

require_once 'global.php';
require_once INC_PATH . '/functions_claim.php';

$lang->load('claim');

if (empty($CURUSER['id'])) {
    if (function_exists('loggedinorreturn')) loggedinorreturn();
    header('Location: ' . $BASEURL . '/login.php');
    exit;
}

$me = (int)$CURUSER['id'];
$e  = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
// Replaces {1}, {2}, ... (or %1$s, %2$s, ... if the lang loader converted them) with the given values
$tr = static function (string $text, ...$args): string {
    foreach ($args as $i => $v) {
        $n    = $i + 1;
        $text = str_replace(['{' . $n . '}', '%' . $n . '$s'], (string)$v, $text);
    }
    return $text;
};

function claim_redirect(string $to, string $type, string $msg): never
{
    setcookie('claim_flash', json_encode(['t' => $type, 'm' => $msg], JSON_UNESCAPED_UNICODE), [
        'expires' => TIMENOW + 60, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure'  => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    header('Location: ' . $to, true, 303);
    exit;
}

// ── Claim / give up (POST) ───────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $tid  = (int)($_POST['torrent_id'] ?? 0);
    $back = (string)($_POST['returnto'] ?? '');
    // Only back to our own pages
    $back = preg_match('#^(details|claim)\.php(\?[^\s<>"]*)?$#', $back) ? $BASEURL . '/' . $back : $BASEURL . '/claim.php';

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        claim_redirect($back, 'danger', $lang->claim['security_expired']);
    }
    try {
        if (($_POST['action'] ?? '') === 'remove') {
            $deduct = claim_give_up($me, $tid);
            write_log("[Claims] {$CURUSER['username']} gave up torrent #{$tid}");
            claim_redirect($back, 'success', $deduct > 0 ? $tr($lang->claim['gave_up_deducted'], number_format($deduct)) : $lang->claim['gave_up']);
        }
        claim_add($me, $tid);
        claim_redirect($back, 'success', $lang->claim['claimed']);
    } catch (ClaimException $ex) {
        claim_redirect($back, 'danger', $ex->getMessage());
    }
}

// ── Whose claims ─────────────────────────────────────────
$torrentId = (int)($_GET['torrent_id'] ?? 0);
$uid       = $torrentId > 0 ? 0 : (int)($_GET['uid'] ?? $me);
$torrent   = null;
$owner     = null;
if ($torrentId > 0) {
    $torrent = $db->fetch_array($db->sql_query_prepared('SELECT id, name, size, added FROM torrents WHERE id = ?', [$torrentId]));
    if (!$torrent) stderr($lang->claim['error'], $lang->claim['torrent_not_found']);
} else {
    // Other users' claims: staff only. The torrent view (?torrent_id=) stays open to everyone.
    $isStaff = isset($usergroups) && function_exists('is_mod') && is_mod($usergroups);
    if ($uid !== $me && !$isStaff) {
        stderr($lang->claim['error'], $lang->claim['only_own'], 403, '403');
    }
    $owner = $uid === $me ? $CURUSER : get_user($uid);
    if (!$owner) stderr($lang->claim['error'], $lang->claim['user_not_found']);
}
$isMine  = $torrentId === 0 && $uid === $me;
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));
$total   = $torrentId > 0 ? claim_count_torrent($torrentId) : claim_count_user($uid);
$rows    = claim_list($torrentId > 0 ? ['torrent_id' => $torrentId] : ['uid' => $uid], $perPage, ($page - 1) * $perPage);
$prevStart = claim_month_start(claim_month_start() - 1);

$flash = null;
if (!empty($_COOKIE['claim_flash'])) {
    $flash = json_decode((string)$_COOKIE['claim_flash'], true);
    setcookie('claim_flash', '', ['expires' => 1, 'path' => '/']);
}
$postKey = function_exists('generate_post_check') ? (string)generate_post_check() : (string)($mybb->post_code ?? '');
$selfUrl = 'claim.php' . ($torrentId > 0 ? '?torrent_id=' . $torrentId : ($isMine ? '' : '?uid=' . $uid));

$userLink = static function (array $r) use ($e, $BASEURL): string {
    $name = $e((string)($r['username'] ?? ''));
    if (function_exists('format_name')) $name = format_name($name, (int)$r['usergroup'], (int)($r['displaygroup'] ?? 0));
    $url = function_exists('get_profile_link') ? (string)get_profile_link((int)$r['uid']) : $BASEURL . '/member.php?action=profile&uid=' . (int)$r['uid'];
    return '<a href="' . $e($url) . '">' . $name . '</a>';
};
$hours = static fn(int $s): string => number_format($s / 3600, $s < 36000 ? 1 : 0);

$reachedN = count(array_filter($rows, static fn($r) => $r['reached']));
$riskN    = count(array_filter($rows, static fn($r) => !$r['reached'] && !$r['first_period']));

$title = $torrentId > 0 ? $lang->claim['title_torrent'] : ($isMine ? $lang->claim['title_mine'] : $tr($lang->claim['title_user'], $owner['username']));
stdhead($title);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/claim.css?ver=1111">

<div class="cl">
  <div class="cl-card cl-head">
    <div class="cl-head__icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
    <div class="cl-head__text">
      <h1><?= $e($title) ?></h1>
      <p><?php if ($torrentId > 0): ?>
          <a href="<?= $BASEURL ?>/<?= get_torrent_link($torrentId) ?>"><?= $e((string)$torrent['name']) ?></a> &middot; <?= mksize((float)$torrent['size']) ?>
		  
		  
        <?php elseif (!$isMine): ?>
          <?= $userLink(['uid' => $uid] + $owner) ?>
        <?php else: ?>
          <?= $e($lang->claim['head_desc']) ?>
        <?php endif; ?></p>
    </div>
  </div>

  <?php if (is_array($flash) && isset($flash['t'], $flash['m'])): ?>
    <div class="cl-alert cl-alert--<?= $flash['t'] === 'success' ? 'success' : 'danger' ?>" role="alert">
      <i class="fa-solid <?= $flash['t'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i><span><?= $e((string)$flash['m']) ?></span>
    </div>
  <?php endif; ?>

  <div class="cl-kpis">
    <div class="cl-card cl-kpi"><span class="cl-kpi__icon cl-tone-primary"><i class="fa-solid fa-list-check"></i></span>
      <div><div class="cl-kpi__value"><?= number_format($total) ?><?= $torrentId > 0 ? ' / ' . CLAIM_MAX_PER_TORRENT : ' / ' . number_format(CLAIM_MAX_PER_USER) ?></div>
      <div class="cl-kpi__label"><?= $torrentId > 0 ? $e($lang->claim['claimers']) : $e($lang->claim['claimed_torrents']) ?></div></div></div>
    <div class="cl-card cl-kpi"><span class="cl-kpi__icon cl-tone-success"><i class="fa-solid fa-circle-check"></i></span>
      <div><div class="cl-kpi__value"><?= number_format($reachedN) ?></div><div class="cl-kpi__label"><?= $e($lang->claim['norm_reached']) ?></div></div></div>
    <div class="cl-card cl-kpi"><span class="cl-kpi__icon cl-tone-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
      <div><div class="cl-kpi__value"><?= number_format($riskN) ?></div><div class="cl-kpi__label"><?= $e($lang->claim['at_risk_removal']) ?></div></div></div>
  </div>

  <details class="cl-card cl-rules"<?= $total === 0 ? ' open' : '' ?>>
    <summary><i class="fa-solid fa-circle-info"></i><?= $e($lang->claim['how_it_works']) ?></summary>
    <ul>
      <li><?= $tr($lang->claim['rule_1'], CLAIM_MIN_AGE_DAYS, number_format(CLAIM_MAX_PER_USER), CLAIM_MAX_PER_TORRENT) ?></li>
      <li><?= $tr($lang->claim['rule_2'], CLAIM_SEED_HOURS, CLAIM_UPLOAD_TIMES) ?></li>
      <li><?= $tr($lang->claim['rule_3'], rtrim(rtrim(number_format(CLAIM_BONUS_PER_HOUR * CLAIM_BONUS_MULTIPLIER, 2), '0'), '.')) ?></li>
      <li><?= $tr($lang->claim['rule_4'], number_format(CLAIM_REMOVE_DEDUCT)) ?></li>
      <li><?= $tr($lang->claim['rule_5'], number_format(CLAIM_GIVE_UP_DEDUCT)) ?></li>
    </ul>
  </details>

  <div class="cl-card cl-table-wrap">
    <?php if (!$rows): ?>
      <div class="cl-empty"><i class="fa-solid fa-seedling"></i>
        <p><?= $e($torrentId > 0 ? $lang->claim['empty_torrent'] : ($isMine ? $lang->claim['empty_mine'] : $lang->claim['empty_other'])) ?></p></div>
    <?php else: ?>
      <table class="cl-table">
        <thead><tr>
          <th><?= $e($torrentId > 0 ? $lang->claim['th_user'] : $lang->claim['th_torrent']) ?></th>
          <th><?= $e($lang->claim['th_claimed']) ?></th>
          <th><?= $e($lang->claim['th_seeded']) ?> <span class="cl-sub"><?= $e($tr($lang->claim['th_seeded_norm'], CLAIM_SEED_HOURS)) ?></span></th>
          <th><?= $e($lang->claim['th_uploaded']) ?> <span class="cl-sub"><?= $e($tr($lang->claim['th_uploaded_norm'], CLAIM_UPLOAD_TIMES)) ?></span></th>
          <th><?= $e($lang->claim['th_status']) ?></th>
          <?php if ($isMine): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            // What happens at the next settlement (date shown under the badge)
            if ($r['reached'])            [$tone, $label] = ['success', $lang->claim['status_reached']];
            elseif ($r['first_period'])   [$tone, $label] = ['info', $lang->claim['status_first']];
            else                          [$tone, $label] = ['danger', $lang->claim['status_risk']];
            $gone = $r['torrent_name'] === null; ?>
          <tr>
            <td><?php if ($torrentId > 0): ?>
                  <?= $userLink($r) ?>
                <?php elseif ($gone): ?>
                  <span class="cl-sub"><?= $e($tr($lang->claim['torrent_deleted'], (int)$r['torrent_id'])) ?></span>
                <?php else: ?>
                  
				  <a class="cl-name" href="<?= $BASEURL ?>/<?= get_torrent_link((int)$r['torrent_id']) ?>"><?= $e((string)$r['torrent_name']) ?></a>
				  
                  <div class="cl-sub"><?= mksize((float)$r['size']) ?> &middot; <?= $e($tr($lang->claim['seeders_count'], (int)$r['seeders'])) ?> &middot; <a href="<?= $BASEURL ?>/claim.php?torrent_id=<?= (int)$r['torrent_id'] ?>"><?= $e($lang->claim['claimers_link']) ?></a></div>
                <?php endif; ?></td>
            <td class="cl-nowrap"><?= date('d.m.Y', (int)$r['added']) ?></td>
            <td class="cl-prog">
              <div class="cl-bar"><div class="cl-bar__fill <?= $r['seed_pct'] >= 100 ? 'is-done' : '' ?>" style="width:<?= $r['seed_pct'] ?>%"></div></div>
              <span class="cl-sub"><?= $hours($r['seed_this_month']) ?> <?= $e($lang->claim['hours_short']) ?></span></td>
            <td class="cl-prog">
              <div class="cl-bar"><div class="cl-bar__fill <?= $r['up_pct'] >= 100 ? 'is-done' : '' ?>" style="width:<?= $r['up_pct'] ?>%"></div></div>
              <span class="cl-sub"><?= mksize((float)$r['up_this_month']) ?></span></td>
            <td><span class="cl-badge cl-tone-<?= $tone ?>"><?= $e($label) ?></span>
              <div class="cl-sub"><?= (int)$r['next_settle'] <= TIMENOW ? $e($lang->claim['result_now']) : $e($tr($lang->claim['result_date'], date('d.m.Y', (int)$r['next_settle']))) ?></div></td>
            <?php if ($isMine): ?>
              <td class="cl-nowrap">
                <form method="post" action="<?= $BASEURL ?>/claim.php" data-cl-confirm="<?= $e($tr($lang->claim['confirm_give_up'], number_format(CLAIM_GIVE_UP_DEDUCT))) ?>">
                  <input type="hidden" name="my_post_key" value="<?= $e($postKey) ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="torrent_id" value="<?= (int)$r['torrent_id'] ?>">
                  <input type="hidden" name="returnto" value="<?= $e($selfUrl) ?>">
                  <button class="cl-btn cl-btn--danger" title="<?= $e($lang->claim['give_up']) ?>"><i class="fa-solid fa-xmark"></i><?= $e($lang->claim['give_up']) ?></button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
    <nav class="cl-pages">
      <?php for ($i = max(1, $page - 5); $i <= min($pages, $page + 5); $i++): ?>
        <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= $BASEURL ?>/<?= $e($selfUrl) ?><?= str_contains($selfUrl, '?') ? '&amp;' : '?' ?>page=<?= $i ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<script>
// Confirm "Give up" / "Claim" (SweetAlert2 if loaded, otherwise the browser dialog)
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f.matches('form[data-cl-confirm]') || f.dataset.ok === '1') return;
  e.preventDefault();
  var go = function () { f.dataset.ok = '1'; f.querySelector('button').disabled = true; f.submit(); };
  if (window.Swal) {
    window.Swal.fire({ icon: 'warning', text: f.dataset.clConfirm, showCancelButton: true, confirmButtonText: <?= json_encode($lang->claim['yes'], JSON_UNESCAPED_UNICODE) ?>,
      cancelButtonText: <?= json_encode($lang->claim['cancel'], JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#dc3545', reverseButtons: true, focusCancel: true })
      .then(function (r) { if (r.isConfirmed) go(); });
  } else if (window.confirm(f.dataset.clConfirm)) { go(); }
});
</script>
<?php
stdfoot();