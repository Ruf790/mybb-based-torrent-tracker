<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

if (session_status() === PHP_SESSION_NONE) session_start();

const CN_VERSION   = 'v.0.3';
const CN_ASSET_VER = 1;
const CN_NAME_MAX  = 60;
const CN_FLAG_RE   = '~^[A-Za-z0-9_.-]{1,60}\.(gif|png|jpe?g|webp|svg)$~i';

/**
 * Escape for output. double_encode=false: старые записи сохранялись уже
 * через htmlspecialchars(), и "&amp;" в БД не должен превратиться в "&amp;amp;".
 */
function cn_e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8', false);
}

function cn_tone(string $c): string
{
    return "--cn-bg:var(--bs-{$c}-bg-subtle);--cn-fg:var(--bs-{$c}-text-emphasis);--cn-solid:var(--bs-{$c})";
}

final class CountryManager
{
    private string $flagDir;
    private string $flagUrl;
    private string $self;

    public function __construct(private readonly object $db, string $baseUrl, string $picBaseUrl)
    {
        $pic           = trim($picBaseUrl, '/\\');
        $this->flagDir = rtrim(TSDIR, '/\\') . '/' . $pic . '/flag/';
        $this->flagUrl = rtrim($baseUrl, '/') . '/' . $pic . '/flag/';
        $this->self    = $_SERVER['SCRIPT_NAME'] . '?act=country';
    }

    // ── Routing ──────────────────────────────────────────────────────────────

    public function handleRequest(): void
    {
        global $mybb;

        $action = (string)($_POST['action'] ?? $_GET['action'] ?? 'list');
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        // Всё, что меняет данные — только POST + CSRF
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_post_check((string)$mybb->get_input('my_post_key'), true)) {
                $this->back('Security check failed. Please try again.', 'danger');
            }
            match ($action) {
                'save'   => $this->save($id),
                'delete' => $this->delete($id),
                default  => $this->back('Unknown action.', 'danger'),
            };
            return;
        }

        match ($action) {
            'new'   => $this->renderForm(['id' => 0, 'name' => '', 'flagpic' => $this->validFlagOrEmpty((string)($_GET['flag'] ?? ''))]),
            'edit'  => $this->renderForm($this->find($id) ?? $this->back('Country not found.', 'danger')),
            default => $this->renderList(),
        };
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    private function find(int $id): ?array
    {
        if ($id <= 0) return null;
        $q = $this->db->sql_query_prepared('SELECT id, name, flagpic FROM countries WHERE id = ?', [$id]);
        $r = $q ? $this->db->fetch_array($q) : null;
        return $r ?: null;
    }

    /** @return list<array{id:int,name:string,flagpic:string}> */
    private function all(): array
    {
        $rows = [];
        $q = $this->db->sql_query_prepared('SELECT id, name, flagpic FROM countries ORDER BY name ASC');
        while ($q && ($r = $this->db->fetch_array($q))) {
            $rows[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'flagpic' => (string)$r['flagpic']];
        }
        return $rows;
    }

    /** @return list<string> flag file names available on disk */
    private function flagFiles(): array
    {
        if (!is_dir($this->flagDir)) return [];
        $files = [];
        foreach (scandir($this->flagDir) ?: [] as $f) {
            if (preg_match(CN_FLAG_RE, $f) && is_file($this->flagDir . $f)) $files[] = $f;
        }
        natcasesort($files);
        return array_values($files);
    }

    private function flagExists(string $file): bool
    {
        return $file !== '' && preg_match(CN_FLAG_RE, $file) === 1 && is_file($this->flagDir . $file);
    }

    private function validFlagOrEmpty(string $file): string
    {
        $file = basename($file);
        return $this->flagExists($file) ? $file : '';
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    private function save(int $id): void
    {
        $isEdit = $id > 0;
        if ($isEdit && !$this->find($id)) $this->back('Country not found.', 'danger');

        $name = trim((string)($_POST['name'] ?? ''));
        $flag = basename(trim((string)($_POST['flagpic'] ?? '')));

        $errors = [];
        if ($name === '')                          $errors[] = 'Enter the country name.';
        elseif (mb_strlen($name) > CN_NAME_MAX)    $errors[] = 'The name is longer than ' . CN_NAME_MAX . ' characters.';
        if ($flag === '')                          $errors[] = 'Pick a flag.';
        elseif (!$this->flagExists($flag))         $errors[] = 'That flag file is not in the flags folder.';

        if (!$errors) {
            $dup = $this->db->sql_query_prepared('SELECT id FROM countries WHERE name = ? AND id != ? LIMIT 1', [$name, $id]);
            if ($dup && $this->db->num_rows($dup) > 0) $errors[] = "“{$name}” is already on the list.";
        }

        if ($errors) {
            $this->renderForm(['id' => $id, 'name' => $name, 'flagpic' => $flag], $errors);
            return;
        }

        if ($isEdit) {
            $this->db->sql_query_prepared('UPDATE countries SET name = ?, flagpic = ? WHERE id = ?', [$name, $flag, $id]);
            write_log("Edited country #{$id} '{$name}' ({$flag}) by " . $this->staff());
            $this->back("{$name} saved.", 'success', $id);
        }

        $this->db->sql_query_prepared('INSERT INTO countries (name, flagpic) VALUES (?, ?)', [$name, $flag]);
        $newId = (int)$this->db->insert_id();
        write_log("Added country #{$newId} '{$name}' ({$flag}) by " . $this->staff());
        $this->back("{$name} added.", 'success', $newId);
    }

    private function delete(int $id): void
    {
        $country = $this->find($id) ?? $this->back('Country not found.', 'danger');

        $this->db->sql_query_prepared('DELETE FROM countries WHERE id = ? LIMIT 1', [$id]);
        write_log("Deleted country #{$id} '{$country['name']}' by " . $this->staff());
        $this->back("{$country['name']} deleted.", 'success');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function staff(): string
    {
        global $CURUSER;
        return (string)($CURUSER['username'] ?? 'unknown');
    }

    /** PRG redirect with a flash toast. */
    private function back(string $msg, string $type = 'success', int $anchorId = 0): never
    {
        $_SESSION['cn_flash'] = ['msg' => $msg, 'type' => $type];
        header('Location: ' . $this->self . ($anchorId > 0 ? '#c' . $anchorId : ''));
        exit;
    }

    private function url(array $params = []): string
    {
        return $this->self . ($params ? '&' . http_build_query($params) : '');
    }

    // ── Page chrome ──────────────────────────────────────────────────────────

    private function open(string $title, string $sub, string $icon, string $tone, string $actions = ''): void
    {
        global $BASEURL, $mybb;
        $v = CN_ASSET_VER;

        stdhead($title);
        ?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/country.css?ver=<?= $v ?>">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/country.js?ver=<?= $v ?>" defer></script>
        <?php
        if (!empty($_SESSION['cn_flash'])):
            $f = $_SESSION['cn_flash'];
            unset($_SESSION['cn_flash']);
            $toast = match ($f['type']) { 'success' => 'success', 'danger' => 'error', 'warning' => 'warning', default => 'info' };
        ?>
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script>document.addEventListener("DOMContentLoaded",function(){ showToast(<?= json_encode($f['msg'], JSON_HEX_TAG) ?>,<?= json_encode($toast) ?>); });</script>
        <?php endif; ?>
<div class="cn-page">
  <div class="container py-3">
    <header class="cn-head" style="<?= cn_tone($tone) ?>">
      <div class="cn-head-icon" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></div>
      <div class="cn-head-text">
        <h1><?= cn_e($title) ?></h1>
        <p><?= cn_e($sub) ?></p>
      </div>
      <?php if ($actions !== ''): ?><div class="cn-head-actions"><?= $actions ?></div><?php endif; ?>
    </header>
        <?php
    }

    private function close(): void
    {
        echo "\n  </div>\n</div>\n";
        stdfoot();
    }

    private function flagImg(string $file, string $alt, string $class = 'cn-flag'): string
    {
        if (!$this->flagExists($file)) {
            return '<span class="' . $class . ' is-missing" title="Flag file missing"><i class="fa-solid fa-image"></i></span>';
        }
        return '<img src="' . cn_e($this->flagUrl . $file) . '" class="' . $class . '" alt="' . cn_e($alt) . '" loading="lazy">';
    }

    // ── List ─────────────────────────────────────────────────────────────────

    private function renderList(): void
    {
        global $mybb;

        $countries = $this->all();
        $flags     = $this->flagFiles();
        $used      = array_flip(array_map(static fn($c) => $c['flagpic'], $countries));
        $unused    = array_values(array_filter($flags, static fn($f) => !isset($used[$f])));
        $missing   = count(array_filter($countries, fn($c) => !$this->flagExists($c['flagpic'])));

        $this->open(
            'Countries',
            'The list members pick from in their profile, with the flag shown next to their name.',
            'fa-earth-europe', 'primary',
            '<a href="' . cn_e($this->url(['action' => 'new'])) . '" class="btn btn-primary rounded-pill px-3">'
            . '<i class="fa-solid fa-plus me-1"></i>Add country</a>'
        );
        ?>
    <section class="cn-kpis">
      <div class="cn-kpi" style="<?= cn_tone('primary') ?>">
        <span class="cn-kpi-icon"><i class="fa-solid fa-earth-europe"></i></span>
        <div><div class="cn-kpi-val"><?= number_format(count($countries)) ?></div><div class="cn-kpi-label">Countries</div></div>
      </div>
      <div class="cn-kpi" style="<?= cn_tone('success') ?>">
        <span class="cn-kpi-icon"><i class="fa-solid fa-flag"></i></span>
        <div><div class="cn-kpi-val"><?= number_format(count($flags)) ?></div><div class="cn-kpi-label">Flag files</div></div>
      </div>
      <div class="cn-kpi" style="<?= cn_tone('info') ?>">
        <span class="cn-kpi-icon"><i class="fa-regular fa-flag"></i></span>
        <div><div class="cn-kpi-val"><?= number_format(count($unused)) ?></div><div class="cn-kpi-label">Flags not used yet</div></div>
      </div>
      <div class="cn-kpi" style="<?= cn_tone($missing ? 'danger' : 'secondary') ?>">
        <span class="cn-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div><div class="cn-kpi-val"><?= number_format($missing) ?></div><div class="cn-kpi-label">Missing flag file</div></div>
      </div>
    </section>

    <section class="cn-card">
      <div class="cn-card-head">
        <h2><i class="fa-solid fa-list me-2"></i>All countries <span class="cn-count"><?= number_format(count($countries)) ?></span></h2>
        <?php if ($countries): ?>
        <label class="cn-search">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <input type="search" class="form-control form-control-sm" placeholder="Filter by name or file"
                 aria-label="Filter countries" data-cn-filter="#cn-table tbody tr">
        </label>
        <?php endif; ?>
      </div>

      <?php if (!$countries): ?>
        <div class="cn-empty">
          <div class="cn-empty-icon" style="<?= cn_tone('primary') ?>"><i class="fa-solid fa-earth-europe"></i></div>
          <h3>No countries yet</h3>
          <p>Add the first one and members can pick it in their profile.</p>
          <a href="<?= cn_e($this->url(['action' => 'new'])) ?>" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i>Add country</a>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table cn-table align-middle mb-0" id="cn-table">
          <thead>
            <tr>
              <th class="cn-col-flag"><i class="fa-solid fa-flag me-1"></i>Flag</th>
              <th><i class="fa-solid fa-heading me-1"></i>Name</th>
              <th class="d-none d-sm-table-cell"><i class="fa-regular fa-file-image me-1"></i>File</th>
              <th class="cn-col-id text-end">ID</th>
              <th class="cn-col-act text-end"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($countries as $c):
              $ok = $this->flagExists($c['flagpic']); ?>
            <tr id="c<?= $c['id'] ?>" data-cn-text="<?= cn_e(mb_strtolower($c['name'] . ' ' . $c['flagpic'])) ?>">
              <td class="cn-col-flag"><?= $this->flagImg($c['flagpic'], $c['name']) ?></td>
              <td class="cn-name"><?= cn_e($c['name']) ?></td>
              <td class="d-none d-sm-table-cell">
                <code class="cn-file"><?= cn_e($c['flagpic']) ?></code>
                <?php if (!$ok): ?><span class="cn-tag" style="<?= cn_tone('danger') ?>"><i class="fa-solid fa-triangle-exclamation"></i>Missing</span><?php endif; ?>
              </td>
              <td class="cn-col-id text-end">#<?= $c['id'] ?></td>
              <td class="cn-col-act text-end">
                <div class="cn-actions">
                  <a href="<?= cn_e($this->url(['action' => 'edit', 'id' => $c['id']])) ?>" class="cn-icon-btn" title="Edit <?= cn_e($c['name']) ?>">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                  <button type="button" class="cn-icon-btn is-danger" title="Delete <?= cn_e($c['name']) ?>"
                          data-cn-delete data-id="<?= $c['id'] ?>" data-name="<?= cn_e($c['name']) ?>">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="cn-empty cn-empty-sm" id="cn-no-match" hidden>
          <p><i class="fa-solid fa-magnifying-glass me-1"></i>No country matches that filter.</p>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <?php if ($unused): ?>
    <section class="cn-card cn-unused">
      <div class="cn-card-head">
        <h2><i class="fa-regular fa-flag me-2"></i>Flags not used yet <span class="cn-count"><?= number_format(count($unused)) ?></span></h2>
        <span class="cn-muted small">Click a flag to add a country with it.</span>
      </div>
      <div class="cn-flag-grid">
        <?php foreach ($unused as $f): ?>
        <a href="<?= cn_e($this->url(['action' => 'new', 'flag' => $f])) ?>" class="cn-flag-tile" title="<?= cn_e($f) ?>">
          <img src="<?= cn_e($this->flagUrl . $f) ?>" alt="" loading="lazy">
          <span><?= cn_e(pathinfo($f, PATHINFO_FILENAME)) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <form method="post" id="cn-delete-form" action="<?= cn_e($this->self) ?>" hidden>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="my_post_key" value="<?= cn_e($mybb->post_code ?? '') ?>">
    </form>
        <?php
        $this->close();
    }

    // ── Add / edit form ──────────────────────────────────────────────────────

    private function renderForm(array $c, array $errors = []): void
    {
        global $mybb;

        $id     = (int)($c['id'] ?? 0);
        $isEdit = $id > 0;
        $name   = (string)($c['name'] ?? '');
        $sel    = (string)($c['flagpic'] ?? '');
        $flags  = $this->flagFiles();

        $this->open(
            $isEdit ? 'Edit country' : 'Add country',
            $isEdit ? 'Renaming changes it for every member who picked this country.'
                    : 'The flag must already be in the flags folder on the server.',
            $isEdit ? 'fa-pen-to-square' : 'fa-plus',
            $isEdit ? 'warning' : 'primary',
            '<a href="' . cn_e($this->self . ($isEdit ? '#c' . $id : '')) . '" class="btn btn-sm btn-outline-secondary rounded-pill px-3">'
            . '<i class="fa-solid fa-arrow-left me-1"></i>All countries</a>'
        );

        if ($errors): ?>
    <div class="cn-alert" role="alert">
      <i class="fa-solid fa-circle-exclamation"></i>
      <ul><?php foreach ($errors as $e): ?><li><?= cn_e($e) ?></li><?php endforeach; ?></ul>
    </div>
        <?php endif; ?>

    <form method="post" action="<?= cn_e($this->self) ?>" class="cn-form" data-cn-form>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="my_post_key" value="<?= cn_e($mybb->post_code ?? '') ?>">

      <div class="cn-editor">
        <section class="cn-card cn-panel">
          <label class="cn-label" for="cn-name"><i class="fa-solid fa-heading"></i>Country name <span class="cn-req">*</span></label>
          <input type="text" class="form-control form-control-lg" id="cn-name" name="name" value="<?= cn_e($name) ?>"
                 maxlength="<?= CN_NAME_MAX ?>" required autocomplete="off" placeholder="Bulgaria">

          <div class="cn-picker-head">
            <label class="cn-label mb-0"><i class="fa-solid fa-flag"></i>Flag <span class="cn-req">*</span></label>
            <label class="cn-search">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              <input type="search" class="form-control form-control-sm" placeholder="Find a flag file"
                     aria-label="Find a flag file" data-cn-filter=".cn-picker .cn-pick">
            </label>
          </div>

          <?php if (!$flags): ?>
            <div class="cn-empty cn-empty-sm">
              <p><i class="fa-solid fa-folder-open me-1"></i>No flag images found in <code><?= cn_e(basename(rtrim($this->flagDir, '/'))) ?>/</code>. Upload the files first.</p>
            </div>
          <?php else: ?>
          <div class="cn-picker" role="radiogroup" aria-label="Flag">
            <?php foreach ($flags as $f): ?>
            <label class="cn-pick" data-cn-text="<?= cn_e(mb_strtolower($f)) ?>" title="<?= cn_e($f) ?>">
              <input type="radio" name="flagpic" value="<?= cn_e($f) ?>" <?= $f === $sel ? 'checked' : '' ?> required
                     data-cn-src="<?= cn_e($this->flagUrl . $f) ?>">
              <img src="<?= cn_e($this->flagUrl . $f) ?>" alt="" loading="lazy">
              <span><?= cn_e(pathinfo($f, PATHINFO_FILENAME)) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="cn-empty cn-empty-sm" id="cn-no-flag" hidden><p>No flag file matches.</p></div>
          <?php endif; ?>
        </section>

        <aside class="cn-card cn-panel cn-preview">
          <h2><i class="fa-solid fa-eye me-2"></i>How members see it</h2>
          <div class="cn-preview-user">
            <span class="cn-preview-flag" id="cn-preview-flag">
              <?= $sel !== '' ? '<img src="' . cn_e($this->flagUrl . $sel) . '" alt="">' : '<i class="fa-regular fa-flag"></i>' ?>
            </span>
            <div>
              <strong id="cn-preview-name"><?= $name !== '' ? cn_e($name) : 'Country name' ?></strong>
              <small id="cn-preview-file"><?= $sel !== '' ? cn_e($sel) : 'No flag picked' ?></small>
            </div>
          </div>
          <?php if ($isEdit): ?>
          <p class="cn-muted small mb-0"><i class="fa-solid fa-hashtag me-1"></i>Country ID <?= $id ?></p>
          <?php endif; ?>
        </aside>
      </div>

      <div class="cn-bar">
        <a href="<?= cn_e($this->self . ($isEdit ? '#c' . $id : '')) ?>" class="btn btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-xmark me-1"></i>Cancel</a>
        <button type="submit" class="btn btn-primary rounded-pill px-4" data-cn-submit>
          <i class="fa-solid <?= $isEdit ? 'fa-floppy-disk' : 'fa-plus' ?> me-1"></i><?= $isEdit ? 'Save changes' : 'Add country' ?>
        </button>
      </div>
    </form>
        <?php
        $this->close();
    }
}

(new CountryManager($db, (string)$BASEURL, (string)$pic_base_url))->handleRequest();