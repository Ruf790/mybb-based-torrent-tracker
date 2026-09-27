<?php
declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

/** Вкл/выкл из любого старого формата: true / 'yes' / 'on' / '1' */
function sb_is_on(mixed $v): bool
{
    return $v === true || in_array(strtolower((string)$v), ['yes', 'on', '1', 'true'], true);
}

// ═══════════════════════════════════════════════════════════
// CLASS
// ═══════════════════════════════════════════════════════════
class SeedbonusSettings
{
    private array $settings = [];
    private array $presets  = [];

    public function __construct()
    {
        $this->loadSettings();
        $this->initializePresets();
    }

    // ── DB ───────────────────────────────────────────────────
    private function loadSettings(): void
    {
        global $db;
        $q = $db->sql_query_prepared('SELECT setting_key, setting_value, setting_type FROM seedbonus_settings');
        while ($q && ($row = $db->fetch_array($q))) {
            $this->settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
        }
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => in_array($value, ['yes', 'true', '1'], true),
            'integer' => (int)$value,
            'float'   => (float)$value,
            'array'   => json_decode($value, true) ?? [],
            default   => (string)$value,
        };
    }

    private function prepareValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean'          => in_array($value, ['yes', 'true', '1', 'on', true], true) ? 'yes' : 'no',
            'integer', 'float' => (string)$value,
            'array'            => json_encode($value, JSON_UNESCAPED_UNICODE),
            default            => (string)$value,
        };
    }

    public function saveSetting(string $key, mixed $value, string $type = 'string'): bool
    {
        global $db;
        $value = $this->prepareValue($value, $type);

        $result = $db->sql_query_prepared("
            INSERT INTO seedbonus_settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type  = VALUES(setting_type),
                updated_at    = CURRENT_TIMESTAMP
        ", [$key, $value, $type]);

        if ($result) $this->settings[$key] = $this->castValue($value, $type);
        return (bool)$result;
    }

    public function loadPreset(string $name): bool
    {
        if (!isset($this->presets[$name])) return false;
        foreach ($this->presets[$name] as $key => $value) {
            $type = is_float($value) ? 'float' : (is_int($value) ? 'integer' : 'string');
            $this->saveSetting($key, $value, $type);
        }
        return true;
    }

    // ── Getters ──────────────────────────────────────────────
    public function getSetting(string $key, mixed $default = null): mixed  { return $this->settings[$key] ?? $default; }
    public function getAllSettings(): array                                  { return $this->settings; }
    public function getPresets(): array                                      { return $this->presets; }
    public function getPreset(string $name): ?array                         { return $this->presets[$name] ?? null; }

    // ── Presets ──────────────────────────────────────────────
    private function initializePresets(): void
    {
        $this->presets = [
            'conservative' => [
                'base_bonus'=>5.0,'hour_cap'=>250.0,'torrent_multiplier_type'=>'penalty','flat_multiplier'=>1.0,
                'leech_none'=>1.0,'leech_few'=>1.2,'leech_many'=>1.5,
                'size_small'=>0.8,'size_medium'=>1.0,'size_large'=>1.2,'size_xlarge'=>1.3,'size_huge'=>1.5,
                'seeders_many'=>0.7,'seeders_medium'=>0.85,'age_old'=>1.2,'age_medium'=>1.1,
                'promo_free'=>0.3,'promo_silver'=>0.2,'promo_double'=>0.2,
            ],
            'balanced' => [
                'base_bonus'=>10.0,'hour_cap'=>500.0,'torrent_multiplier_type'=>'penalty','flat_multiplier'=>1.0,
                'leech_none'=>1.2,'leech_few'=>1.5,'leech_many'=>1.8,
                'size_small'=>1.0,'size_medium'=>1.2,'size_large'=>1.5,'size_xlarge'=>1.8,'size_huge'=>2.0,
                'seeders_many'=>0.9,'seeders_medium'=>0.95,'age_old'=>1.5,'age_medium'=>1.3,
                'promo_free'=>0.7,'promo_silver'=>0.5,'promo_double'=>0.5,
            ],
            'generous' => [
                'base_bonus'=>15.0,'hour_cap'=>1000.0,'torrent_multiplier_type'=>'reward','flat_multiplier'=>1.0,
                'leech_none'=>1.5,'leech_few'=>1.8,'leech_many'=>2.2,
                'size_small'=>1.2,'size_medium'=>1.5,'size_large'=>1.8,'size_xlarge'=>2.0,'size_huge'=>2.5,
                'seeders_many'=>0.95,'seeders_medium'=>1.0,'age_old'=>1.8,'age_medium'=>1.5,
                'promo_free'=>1.0,'promo_silver'=>0.7,'promo_double'=>0.7,
            ],
            'avistaz' => [
                'base_bonus'=>12.0,'hour_cap'=>750.0,'torrent_multiplier_type'=>'reward','flat_multiplier'=>1.0,
                'leech_none'=>1.8,'leech_few'=>2.0,'leech_many'=>2.5,
                'size_small'=>1.5,'size_medium'=>1.8,'size_large'=>2.0,'size_xlarge'=>2.2,'size_huge'=>2.5,
                'seeders_many'=>1.0,'seeders_medium'=>1.0,'age_old'=>2.0,'age_medium'=>1.5,
                'promo_free'=>1.2,'promo_silver'=>0.8,'promo_double'=>0.8,
            ],
            'maximum' => [
                'base_bonus'=>20.0,'hour_cap'=>2000.0,'torrent_multiplier_type'=>'reward','flat_multiplier'=>1.0,
                'leech_none'=>2.0,'leech_few'=>2.5,'leech_many'=>3.0,
                'size_small'=>1.8,'size_medium'=>2.0,'size_large'=>2.2,'size_xlarge'=>2.5,'size_huge'=>3.0,
                'seeders_many'=>1.0,'seeders_medium'=>1.0,'age_old'=>2.5,'age_medium'=>2.0,
                'promo_free'=>1.5,'promo_silver'=>1.0,'promo_double'=>1.0,
            ],
        ];
    }

    // ── Preview ──────────────────────────────────────────────
    public function calculatePreview(): array
    {
        $baseBonus       = (float)$this->getSetting('base_bonus', 2.5);
        $hourCap         = (float)$this->getSetting('hour_cap', 250);
        $multiplierType  = (string)$this->getSetting('torrent_multiplier_type', 'penalty');
        $cronInterval    = (int)$this->getSetting('cron_interval', 15);
        $enableHeuristic = sb_is_on($this->getSetting('enable_heuristic', true));

        $testTorrents = 42;
        $testRawBonus = 95.1;

        $capMul            = $this->torrentMultiplier($testTorrents, $multiplierType);
        $hourlyTheoretical = $testRawBonus * $baseBonus * $capMul;
        $finalHourly       = min($hourlyTheoretical, $hourCap);
        $avgHours          = $this->seedingHours($testTorrents, $cronInterval, $enableHeuristic);
        $perRun            = $finalHourly * $avgHours;
        $realHourly        = $perRun * (60 / $cronInterval);

        return [
            'torrents'            => $testTorrents,
            'raw_bonus'           => $testRawBonus,
            'base_bonus'          => $baseBonus,
            'cap_mul'             => $capMul,
            'hourly_theoretical'  => round($hourlyTheoretical, 1),
            'hour_cap'            => $hourCap,
            'avg_hours'           => round($avgHours, 3),
            'final_hourly'        => round($finalHourly, 1),
            'real_hourly'         => round($realHourly, 1),
            'per_run'             => round($perRun, 2),
            'daily'               => round($realHourly * 24, 0),
        ];
    }

    private function torrentMultiplier(int $torrents, string $type): float
    {
        $flat = (float)$this->getSetting('flat_multiplier', 1.0);
        return match ($type) {
            'penalty' => $torrents <= 20 ? 1.0 : ($torrents <= 50 ? 0.9 : ($torrents <= 100 ? 0.8 : 0.7)),
            'neutral' => $torrents <= 100 ? 1.0 : 0.9,
            'reward'  => $torrents >= 100 ? 1.2 : ($torrents >= 50 ? 1.1 : ($torrents >= 20 ? 1.0 : 0.9)),
            'flat'    => $flat,
            default   => 1.0,
        };
    }

    private function seedingHours(int $torrents, int $cronInterval, bool $heuristic): float
    {
        $max = $cronInterval / 60;
        if ($heuristic) {
            $hoursPerDay      = (float)$this->heuristicHours($torrents);
            $hoursPerInterval = $hoursPerDay * ($cronInterval / 60 / 24);
        } else {
            $hoursPerInterval = $max;
        }
        return min($hoursPerInterval, $max);
    }

    private function heuristicHours(int $torrents): float
    {
        if ($torrents >= 50) return (float)$this->getSetting('heuristic_50', 24);
        if ($torrents >= 40) return (float)$this->getSetting('heuristic_40', 20);
        if ($torrents >= 30) return (float)$this->getSetting('heuristic_30', 16);
        if ($torrents >= 20) return (float)$this->getSetting('heuristic_20', 12);
        if ($torrents >= 10) return (float)$this->getSetting('heuristic_10', 8);
        if ($torrents >= 5)  return (float)$this->getSetting('heuristic_5',  4);
        return (float)$this->getSetting('heuristic_1', 2);
    }

    // ── Config code ──────────────────────────────────────────
    public function generateConfigCode(): string
    {
        $baseBonus        = $this->getSetting('base_bonus', 10.0);
        $hourCap          = $this->getSetting('hour_cap', 500.0);
        $cronInterval     = $this->getSetting('cron_interval', 15) * 60;
        $announceInterval = $this->getSetting('announce_interval', 15) * 60;
        $multiplierType   = $this->getSetting('torrent_multiplier_type', 'penalty');
        $flat             = sprintf('%.1f', $this->getSetting('flat_multiplier', 1.0));

        $torrentCode = match ($multiplierType) {
            'penalty' => "\$user_cap_mul = \$user_torrents <= 20 ? 1.0 : (\$user_torrents <= 50 ? 0.9 : (\$user_torrents <= 100 ? 0.8 : 0.7));",
            'neutral' => "\$user_cap_mul = \$user_torrents <= 100 ? 1.0 : 0.9;",
            'reward'  => "\$user_cap_mul = \$user_torrents >= 100 ? 1.2 : (\$user_torrents >= 50 ? 1.1 : (\$user_torrents >= 20 ? 1.0 : 0.9));",
            'flat'    => "\$user_cap_mul = {$flat};",
            default   => "\$user_cap_mul = 1.0;",
        };

        $h = fn(string $k, float $d) => $this->getSetting($k, $d);

        return <<<PHP
// ===== SEEDBONUS CRON SETTINGS =====
\$ANNOUNCE_INTERVAL   = {$announceInterval};
\$CRON_INTERVAL_SEC   = {$cronInterval};
\$BASE_BONUS          = {$baseBonus};
\$HOUR_CAP            = {$hourCap};
\$MAX_DB_VALUE        = 9999999.9;
\$BATCH_SIZE          = 100;

// Torrent count multiplier
{$torrentCode}

// Seeding time heuristic
\$user_avg_hours = match(true) {
    \$user_torrents >= 50 => {$h('heuristic_50', 24.0)},
    \$user_torrents >= 40 => {$h('heuristic_40', 20.0)},
    \$user_torrents >= 30 => {$h('heuristic_30', 16.0)},
    \$user_torrents >= 20 => {$h('heuristic_20', 12.0)},
    \$user_torrents >= 10 => {$h('heuristic_10', 8.0)},
    \$user_torrents >= 5  => {$h('heuristic_5',  4.0)},
    default               => {$h('heuristic_1',  2.0)},
};
PHP;
    }
}

// ═══════════════════════════════════════════════════════════
// INIT + POST HANDLER
// ═══════════════════════════════════════════════════════════
$seedbonus = new SeedbonusSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Явная проверка прав - на всякий случай, если STAFF_PANEL определяется
    // где-то ещё без сверки конкретно этой возможности.
    if (empty($CURUSER['id']) || !is_mod($usergroups)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied. Staff only.']);
        exit;
    }

    // CSRF - раньше отсутствовала полностью. Без неё сторонняя страница
    // могла бы от имени залогиненного админа поменять общесайтовые
    // настройки бонусной экономики без его ведома.
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    $response = match ($action) {
        'save' => (function () use ($seedbonus): array {
            foreach ($_POST as $key => $value) {
                if (str_starts_with($key, 'seedbonus_')) {
                    $cleanKey = substr($key, 10);
                    $type = match (true) {
                        is_numeric($value) && str_contains($value, '.') => 'float',
                        is_numeric($value)                               => 'integer',
                        in_array($value, ['yes', 'no'], true)            => 'boolean',
                        default                                          => 'string',
                    };
                    $seedbonus->saveSetting($cleanKey, $value, $type);
                }
            }
            return ['success' => true, 'message' => 'Settings saved successfully'];
        })(),
        'load_preset' => (function () use ($seedbonus): array {
            $preset = $_POST['preset'] ?? '';
            return $seedbonus->loadPreset($preset)
                ? ['success' => true,  'message' => "Preset '{$preset}' loaded"]
                : ['success' => false, 'message' => 'Invalid preset'];
        })(),
        default => ['success' => false, 'message' => 'Invalid action'],
    };

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ═══════════════════════════════════════════════════════════
// VIEW DATA
// ═══════════════════════════════════════════════════════════
$preview    = $seedbonus->calculatePreview();
$configCode = $seedbonus->generateConfigCode();

$s = fn(string $k, mixed $d = null) => htmlspecialchars((string)$seedbonus->getSetting($k, $d));
$n = fn(mixed $v, int $dec = 1)     => htmlspecialchars(number_format((float)$v, $dec));

// Реальное число сидирующих пользователей — стартовое значение для прогноза инфляции
// (раньше в одном блоке было «100 users», а в соседнем — «500 users»)
$activeSeeders = 100;
$q = $db->sql_query_prepared("SELECT COUNT(DISTINCT userid) AS n FROM peers WHERE seeder = 'yes'");
if ($q && ($r = $db->fetch_array($q)) && (int)$r['n'] > 0) {
    $activeSeeders = (int)$r['n'];
}

$enabled   = (bool)$seedbonus->getSetting('enabled', true);
$heuristic = sb_is_on($seedbonus->getSetting('enable_heuristic', true));
$cronMin   = max(1, (int)$seedbonus->getSetting('cron_interval', 15));
$daily     = (float)$preview['daily'];
$dailyAll  = $daily * $activeSeeders;

$presetInfo = [
    'conservative' => ['fa-shield-halved',  'ic-blue',   'Careful economy, slow growth'],
    'balanced'     => ['fa-scale-balanced', 'ic-green',  'Recommended default'],
    'generous'     => ['fa-gift',           'ic-amber',  'Rewards heavy seeders'],
    'avistaz'      => ['fa-star',           'ic-purple', 'AvistaZ-style multipliers'],
    'maximum'      => ['fa-rocket',         'ic-red',    'Very fast points — watch inflation'],
];

stdhead('Seedbonus System Settings');
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/seedbonus_settings.css">
<style>
.sb .sb-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color-translucent); border-radius: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.sb .sb-head { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; }
.sb .sb-head-icon, .sb .sb-sec-icon, .sb .sb-kpi-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sb .sb-head-icon { width: 48px; height: 48px; font-size: 1.35rem; border-radius: .85rem; }
.sb .sb-title { font-size: 1.4rem; font-weight: 700; margin: 0; }
.sb .sb-sub { color: var(--bs-secondary-color); font-size: .95rem; }
.sb .sb-muted { font-size: .86rem; color: var(--bs-secondary-color); }
.sb .ic-blue   { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.12); }
.sb .ic-green  { color: #16a34a; background: rgba(34,197,94,.12); }
.sb .ic-amber  { color: #d97706; background: rgba(245,158,11,.14); }
.sb .ic-red    { color: #dc2626; background: rgba(239,68,68,.12); }
.sb .ic-purple { color: #7c3aed; background: rgba(124,58,237,.12); }
.sb .ic-teal   { color: #0891b2; background: rgba(8,145,178,.12); }
.sb .ic-slate  { color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
.sb .btn { border-radius: 50rem; }
.sb .form-control, .sb .form-select { border-radius: .6rem; }

.sb .sb-status { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .8rem; border-radius: 50rem; font-weight: 700; font-size: .85rem; }
.sb .sb-status.on  { color: #15803d; background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.35); }
.sb .sb-status.off { color: #b91c1c; background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.35); }

/* KPI полоса */
.sb .sb-kpi { display: flex; align-items: center; gap: .8rem; padding: .9rem 1.1rem; height: 100%; }
.sb .sb-kpi-icon { width: 42px; height: 42px; border-radius: .8rem; font-size: 1.1rem; }
.sb .sb-kpi-label { font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); }
.sb .sb-kpi-value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }

/* Пресеты */
.sb .sb-presets { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .6rem; }
.sb .config-badge.sb-preset { display: flex; align-items: center; gap: .65rem; padding: .7rem .8rem; border-radius: .85rem; border: 1px solid var(--bs-border-color-translucent); background: var(--bs-body-bg) !important; color: var(--bs-body-color) !important; cursor: pointer; text-align: left; white-space: normal; font-weight: 400; font-size: 1rem; transition: border-color .15s ease, box-shadow .15s ease; }
.sb .config-badge.sb-preset:hover { border-color: rgba(var(--bs-primary-rgb), .45); box-shadow: 0 .3rem .8rem rgba(0,0,0,.06); }
.sb .config-badge.sb-preset.is-active { border-color: var(--bs-primary); box-shadow: 0 0 0 .15rem rgba(var(--bs-primary-rgb), .15); }
.sb .config-badge.sb-preset.is-active::after { content: '\f00c'; font: var(--fa-font-solid); margin-left: auto; color: var(--bs-primary); }
.sb .sb-preset .sb-sec-icon { width: 34px; height: 34px; border-radius: .65rem; font-size: .9rem; }
.sb .sb-preset b { display: block; font-size: .95rem; }
.sb .sb-preset small { color: var(--bs-secondary-color); font-size: .78rem; line-height: 1.3; display: block; }

/* Вкладки */
.sb .sb-tabs { display: flex; gap: .3rem; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; padding: .3rem; border-radius: 50rem; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color-translucent); width: max-content; max-width: 100%; margin: 0 0 1rem; }
.sb .sb-tabs::-webkit-scrollbar { display: none; }
.sb .sb-tabs .nav-link { display: inline-flex; align-items: center; gap: .45rem; padding: .5rem 1rem; border-radius: 50rem; font-weight: 600; color: var(--bs-secondary-color); white-space: nowrap; border: 0; background: transparent; }
.sb .sb-tabs .nav-link:hover { color: var(--bs-body-color); background: var(--bs-body-bg); }
.sb .sb-tabs .nav-link.active { color: #fff; background: var(--bs-primary); box-shadow: 0 .25rem .75rem rgba(var(--bs-primary-rgb), .3); }

/* Секции настроек */
.sb .sb-sec { height: 100%; }
.sb .sb-sec-head { display: flex; align-items: center; gap: .65rem; padding: .9rem 1.1rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
.sb .sb-sec-icon { width: 36px; height: 36px; border-radius: .7rem; font-size: .95rem; }
.sb .sb-sec-title { font-weight: 700; font-size: 1.02rem; margin: 0; }
.sb .sb-sec-body { padding: 1rem 1.1rem; }
.sb .sb-group { font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 1rem 0 .5rem; }
.sb .sb-group:first-child { margin-top: 0; }
.sb .sb-field { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .35rem 0; }
.sb .sb-field label { margin: 0; font-size: .93rem; }
.sb .sb-field .input-group { width: 130px; flex-shrink: 0; }
.sb .sb-field .form-control { text-align: center; }
.sb .sb-field .input-group-text { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: .8rem; }

.sb .sb-range-value { display: inline-block; min-width: 3.5rem; padding: .05rem .5rem; border-radius: .5rem; background: var(--bs-tertiary-bg); font-weight: 700; text-align: center; }
.sb .sb-marks { display: flex; justify-content: space-between; font-size: .75rem; color: var(--bs-secondary-color); margin-top: .15rem; }

/* Мастер-выключатель */
.sb .sb-master { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.2rem; border-radius: 1rem; border: 2px solid; }
.sb .sb-master.on  { border-color: rgba(34,197,94,.45); background: rgba(34,197,94,.05); }
.sb .sb-master.off { border-color: rgba(239,68,68,.45); background: rgba(239,68,68,.05); }
.sb .sb-master .form-check-input { width: 3em; height: 1.6em; cursor: pointer; margin: 0; }

/* Тип множителя — карточки-радио */
.sb .sb-mtype { display: block; height: 100%; padding: .9rem 1rem; border-radius: .9rem; border: 2px solid var(--bs-border-color-translucent); cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
.sb .sb-mtype:hover { border-color: rgba(var(--bs-primary-rgb), .35); }
.sb .sb-mtype:has(input:checked) { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .05); }
.sb .sb-mtype .form-check-input { margin: 0 .5rem 0 0; }
.sb .sb-mtype ul { list-style: none; padding: 0; margin: .5rem 0 0; font-size: .85rem; color: var(--bs-secondary-color); }
.sb .sb-mtype li { display: flex; justify-content: space-between; padding: .1rem 0; }
.sb .sb-mtype li b { color: var(--bs-body-color); }

/* Превью */
.sb .sb-row { display: flex; justify-content: space-between; padding: .45rem 0; border-bottom: 1px dashed var(--bs-border-color-translucent); font-size: .93rem; }
.sb .sb-row:last-child { border-bottom: 0; }
.sb .sb-big { text-align: center; padding: .9rem; border-radius: .9rem; background: var(--bs-tertiary-bg); height: 100%; }
.sb .sb-big .v { font-size: 1.45rem; font-weight: 700; line-height: 1.2; }
.sb .sb-savebar { position: sticky; bottom: .75rem; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 1rem; margin-top: 1rem; box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08); }
.sb pre.sb-code { margin: 0; padding: 1rem; border-radius: 0 0 1rem 1rem; background: var(--bs-tertiary-bg); font-size: .82rem; max-height: 360px; overflow: auto; }
</style>
<script>var myPostKey = <?= json_encode($mybb->post_code) ?>;</script>

<div class="container mt-3 mb-4 sb">

    <!-- Шапка -->
    <div class="sb-card mb-3"><div class="sb-head">
        <span class="sb-head-icon ic-amber"><i class="fa-solid fa-coins"></i></span>
        <div style="min-width:0">
            <h1 class="sb-title">Seedbonus Settings</h1>
            <div class="sb-sub">How many bonus points seeders earn — rates, multipliers, limits and cron timing</div>
        </div>
        <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
            <span class="sb-status <?= $enabled ? 'on' : 'off' ?>"><i class="fa-solid fa-power-off"></i><?= $enabled ? 'System ON' : 'System OFF' ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="resetBtn"><i class="fa-solid fa-rotate-left me-1"></i>Reset</button>
            <button type="button" class="btn btn-sm btn-primary px-3" id="saveBtn"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
        </div>
    </div></div>

    <!-- KPI -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="sb-card sb-kpi"><span class="sb-kpi-icon ic-amber"><i class="fa-solid fa-coins"></i></span>
            <div><div class="sb-kpi-label">Base bonus</div><div class="sb-kpi-value"><?= $n($preview['base_bonus']) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="sb-card sb-kpi"><span class="sb-kpi-icon ic-red"><i class="fa-solid fa-gauge-high"></i></span>
            <div><div class="sb-kpi-label">Hour cap</div><div class="sb-kpi-value"><?= $n($preview['hour_cap'], 0) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="sb-card sb-kpi"><span class="sb-kpi-icon ic-teal"><i class="fa-solid fa-clock"></i></span>
            <div><div class="sb-kpi-label">Cron every</div><div class="sb-kpi-value"><?= $cronMin ?> min</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="sb-card sb-kpi"><span class="sb-kpi-icon ic-green"><i class="fa-solid fa-chart-line"></i></span>
            <div><div class="sb-kpi-label">Test user / day</div><div class="sb-kpi-value"><?= number_format($daily) ?></div></div></div></div>
    </div>

    <!-- Пресеты -->
    <div class="sb-card mb-3">
        <div class="sb-sec-head"><span class="sb-sec-icon ic-purple"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
            <div><h2 class="sb-sec-title">Quick presets</h2><div class="sb-muted">One click fills rates and multipliers — review and Save</div></div></div>
        <div class="sb-sec-body">
            <div class="sb-presets">
            <?php foreach ($seedbonus->getPresets() as $preset => $vals):
                [$pi, $pc, $pd] = $presetInfo[$preset] ?? ['fa-sliders', 'ic-slate', '']; ?>
                <!-- класс config-badge и data-preset — их слушает seedbonus_settings.js -->
                <button type="button" class="config-badge sb-preset" data-preset="<?= htmlspecialchars($preset) ?>">
                    <span class="sb-sec-icon <?= $pc ?>"><i class="fa-solid <?= $pi ?>"></i></span>
                    <span><b><?= htmlspecialchars(ucfirst($preset)) ?></b><small><?= htmlspecialchars($pd) ?> · base <?= $vals['base_bonus'] ?>, cap <?= (int)$vals['hour_cap'] ?></small></span>
                </button>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Вкладки -->
    <ul class="nav sb-tabs" role="tablist">
        <?php foreach (['basic'=>['fa-sliders','Basic'],'multipliers'=>['fa-percent','Multipliers'],'time'=>['fa-clock','Time'],'preview'=>['fa-eye','Preview']] as $id=>[$icon,$label]): ?>
        <li class="nav-item">
            <button class="nav-link <?= $id === 'basic' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= $id ?>" type="button" role="tab">
                <i class="fa-solid <?= $icon ?>"></i><?= $label ?>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">

        <!-- ── Basic ─────────────────────────────────────── -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <form id="basicForm">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="sb-master <?= $enabled ? 'on' : 'off' ?>" for="systemEnabled" id="sbMaster">
                            <span class="sb-sec-icon <?= $enabled ? 'ic-green' : 'ic-red' ?>" style="width:44px;height:44px;font-size:1.15rem"><i class="fa-solid fa-power-off"></i></span>
                            <span class="flex-grow-1">
                                <span class="fw-bold d-block">Seedbonus system</span>
                                <span class="sb-muted">When off, the cron awards no points at all, whatever the other settings are.</span>
                            </span>
                            <input type="hidden" name="seedbonus_enabled" value="no">
                            <input class="form-check-input" type="checkbox" role="switch" id="systemEnabled" name="seedbonus_enabled" value="yes" <?= $enabled ? 'checked' : '' ?>>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-amber"><i class="fa-solid fa-coins"></i></span>
                                <div><h3 class="sb-sec-title">Base bonus</h3><div class="sb-muted">Main multiplier — higher means more points</div></div></div>
                            <div class="sb-sec-body">
                                <label class="form-label" for="baseBonus">Points per hour: <span class="sb-range-value" id="baseBonusValue"><?= $s('base_bonus', 10.0) ?></span></label>
                                <input type="range" class="form-range" id="baseBonus" name="seedbonus_base_bonus" min="1" max="30" step="0.5" value="<?= $s('base_bonus', 10.0) ?>">
                                <div class="sb-marks"><span>1</span><span>Conservative</span><span>15</span><span>Generous</span><span>30</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-red"><i class="fa-solid fa-gauge-high"></i></span>
                                <div><h3 class="sb-sec-title">Hour cap</h3><div class="sb-muted">Max points per user per hour — abuse protection</div></div></div>
                            <div class="sb-sec-body">
                                <label class="form-label" for="hourCap">Max per hour: <span class="sb-range-value" id="hourCapValue"><?= $s('hour_cap', 500.0) ?></span></label>
                                <input type="range" class="form-range" id="hourCap" name="seedbonus_hour_cap" min="100" max="5000" step="50" value="<?= $s('hour_cap', 500.0) ?>">
                                <div class="sb-marks"><span>100</span><span>Strict</span><span>1000</span><span>Generous</span><span>5000</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-green"><i class="fa-solid fa-layer-group"></i></span>
                                <div><h3 class="sb-sec-title">Torrent count multiplier</h3><div class="sb-muted">How the number of seeded torrents changes the bonus</div></div></div>
                            <div class="sb-sec-body">
                                <div class="row g-3">
                                <?php
                                $types = [
                                    'penalty' => ['fa-arrow-trend-down', 'Penalty for many', ['1–20' => '100%', '21–50' => '90%', '51–100' => '80%', '100+' => '70%']],
                                    'neutral' => ['fa-equals',           'Neutral',          ['1–100' => '100%', '101+' => '90%']],
                                    'reward'  => ['fa-arrow-trend-up',   'Reward for many',  ['1–19' => '90%', '20–49' => '100%', '50–99' => '110%', '100+' => '120%']],
                                    'flat'    => ['fa-lock',             'Fixed',            []],
                                ];
                                $current = $seedbonus->getSetting('torrent_multiplier_type', 'penalty');
                                foreach ($types as $type => [$ic, $label, $rows]): ?>
                                    <div class="col-sm-6 col-lg-3">
                                        <label class="sb-mtype" for="mult<?= ucfirst($type) ?>">
                                            <span class="d-flex align-items-center">
                                                <input class="form-check-input" type="radio" name="seedbonus_torrent_multiplier_type" id="mult<?= ucfirst($type) ?>" value="<?= $type ?>" <?= $current === $type ? 'checked' : '' ?>>
                                                <i class="fa-solid <?= $ic ?> me-2 text-body-secondary"></i><strong><?= $label ?></strong>
                                            </span>
                                            <?php if ($rows): ?>
                                            <ul><?php foreach ($rows as $r => $v): ?><li><span><?= $r ?> torrents</span><b><?= $v ?></b></li><?php endforeach; ?></ul>
                                            <?php else: ?>
                                            <div class="input-group input-group-sm mt-2">
                                                <span class="input-group-text">Always ×</span>
                                                <input type="number" class="form-control" name="seedbonus_flat_multiplier" value="<?= $s('flat_multiplier', 1.0) ?>" step="0.1" min="0.1" max="2.0">
                                            </div>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Multipliers ───────────────────────────────── -->
        <div class="tab-pane fade" id="multipliers" role="tabpanel">
            <form id="multipliersForm">
                <div class="row g-3">
                    <?php
                    $field = function (string $name, string $label, mixed $def, float $step, float $min, float $max, string $unit = '×') use ($s): string {
                        return '<div class="sb-field"><label for="f_' . $name . '">' . $label . '</label>'
                             . '<div class="input-group input-group-sm"><input type="number" class="form-control" id="f_' . $name . '" name="seedbonus_' . $name . '" value="' . $s($name, $def) . '" step="' . $step . '" min="' . $min . '" max="' . $max . '">'
                             . '<span class="input-group-text">' . $unit . '</span></div></div>';
                    };
                    ?>
                    <div class="col-lg-4">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-red"><i class="fa-solid fa-download"></i></span>
                                <div><h3 class="sb-sec-title">Leechers</h3><div class="sb-muted">Reward seeding what people are downloading</div></div></div>
                            <div class="sb-sec-body">
                                <?= $field('leech_none', '<i class="fa-solid fa-user-slash me-1 text-body-secondary"></i>No leechers', 1.2, 0.1, 0.5, 3.0) ?>
                                <?= $field('leech_few',  '<i class="fa-solid fa-user me-1 text-body-secondary"></i>1–2 leechers', 1.5, 0.1, 0.5, 3.0) ?>
                                <?= $field('leech_many', '<i class="fa-solid fa-users me-1 text-body-secondary"></i>3+ leechers', 1.8, 0.1, 0.5, 3.0) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-blue"><i class="fa-solid fa-hard-drive"></i></span>
                                <div><h3 class="sb-sec-title">Torrent size</h3><div class="sb-muted">Reward seeding big files</div></div></div>
                            <div class="sb-sec-body">
                                <?php foreach (['small'=>'&lt; 0.5 GB','medium'=>'&lt; 2 GB','large'=>'&lt; 8 GB','xlarge'=>'&lt; 20 GB','huge'=>'≥ 20 GB'] as $k=>$lbl): ?>
                                <?= $field("size_$k", $lbl, 1.0, 0.1, 0.5, 3.0) ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-purple"><i class="fa-solid fa-star"></i></span>
                                <div><h3 class="sb-sec-title">Extra modifiers</h3><div class="sb-muted">Swarm size, age and promotions</div></div></div>
                            <div class="sb-sec-body">
                                <div class="sb-group"><i class="fa-solid fa-people-group me-1"></i>Many seeders (penalty)</div>
                                <?= $field('seeders_many',   '&gt; 100 seeders', 0.9,  0.05, 0.1, 1.0) ?>
                                <?= $field('seeders_medium', '&gt; 50 seeders',  0.95, 0.05, 0.1, 1.0) ?>
                                <div class="sb-group"><i class="fa-solid fa-hourglass-half me-1"></i>Torrent age</div>
                                <?= $field('age_old',    '&gt; 180 days', 1.5, 0.1, 1.0, 3.0) ?>
                                <?= $field('age_medium', '&gt; 60 days',  1.3, 0.1, 1.0, 3.0) ?>
                                <div class="sb-group"><i class="fa-solid fa-tags me-1"></i>Promo torrents</div>
                                <?= $field('promo_free',   'Freeleech',     0.7, 0.1, 0, 2.0, '+') ?>
                                <?= $field('promo_silver', 'Silver (50%)',  0.5, 0.1, 0, 2.0, '+') ?>
                                <?= $field('promo_double', 'Double upload', 0.5, 0.1, 0, 2.0, '+') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Time ──────────────────────────────────────── -->
        <div class="tab-pane fade" id="time" role="tabpanel">
            <form id="timeForm">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-teal"><i class="fa-solid fa-clock-rotate-left"></i></span>
                                <div><h3 class="sb-sec-title">Intervals</h3><div class="sb-muted">How often the cron runs and peers announce</div></div></div>
                            <div class="sb-sec-body">
                            <?php foreach ([
                                ['cronInterval',     'seedbonus_cron_interval',     'Cron interval',     'cronIntervalValue',     5, 60, 5, 15, 'min',  ['5','15','30','45','60']],
                                ['announceInterval', 'seedbonus_announce_interval', 'Announce interval', 'announceIntervalValue', 5, 60, 5, 15, 'min',  ['5','15','30','45','60']],
                                ['historyDays',      'seedbonus_history_days',      'Activity history',  'historyDaysValue',      1, 30, 1, 1,  'days', ['1','15','30']],
                            ] as [$id, $name, $lbl, $valId, $min, $max, $step, $def, $unit, $marks]):
                                $val = $s(substr($name, 10), $def); ?>
                                <div class="mb-4">
                                    <label class="form-label" for="<?= $id ?>"><?= $lbl ?>: <span class="sb-range-value" id="<?= $valId ?>"><?= $val ?></span> <?= $unit ?></label>
                                    <input type="range" class="form-range" id="<?= $id ?>" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= $val ?>">
                                    <div class="sb-marks"><?php foreach ($marks as $m): ?><span><?= $m ?></span><?php endforeach; ?></div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="sb-card sb-sec">
                            <div class="sb-sec-head"><span class="sb-sec-icon ic-amber"><i class="fa-solid fa-user-clock"></i></span>
                                <div><h3 class="sb-sec-title">Seeding time heuristic</h3><div class="sb-muted">Estimated hours per day by number of torrents</div></div></div>
                            <div class="sb-sec-body">
                                <div class="form-check form-switch mb-3">
                                    <!-- Раньше у переключателя не было ни value, ни скрытого «no»:
                                         включённый сохранялся строкой «on», а выключенный вообще
                                         не отправлялся — отключить эвристику было невозможно -->
                                    <input type="hidden" name="seedbonus_enable_heuristic" value="no">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enableHeuristic" name="seedbonus_enable_heuristic" value="yes" <?= $heuristic ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="enableHeuristic">Use heuristic <span class="text-body-secondary fw-normal">(recommended)</span></label>
                                </div>
                                <?php foreach (['50'=>'≥ 50','40'=>'≥ 40','30'=>'≥ 30','20'=>'≥ 20','10'=>'≥ 10','5'=>'≥ 5','1'=>'1–4'] as $k=>$lbl): ?>
                                <div class="sb-field">
                                    <label for="h_<?= $k ?>"><i class="fa-solid fa-magnet me-1 text-body-secondary"></i><?= $lbl ?> torrents</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="h_<?= $k ?>" name="seedbonus_heuristic_<?= $k ?>" value="<?= $s("heuristic_$k", 24.0) ?>" step="1" min="1" max="24">
                                        <span class="input-group-text">h/day</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Preview ───────────────────────────────────── -->
        <div class="tab-pane fade" id="preview" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="sb-card sb-sec">
                        <div class="sb-sec-head"><span class="sb-sec-icon ic-blue"><i class="fa-solid fa-calculator"></i></span>
                            <div><h3 class="sb-sec-title">Test calculation</h3><div class="sb-muted">Simulated user with <?= (int)$preview['torrents'] ?> torrents, saved settings</div></div></div>
                        <div class="sb-sec-body">
                            <?php foreach ([
                                ['fa-coins',          'Base rate',               $n($preview['base_bonus'])],
                                ['fa-gem',            'Raw bonus',               $n($preview['raw_bonus'])],
                                ['fa-layer-group',    'Torrent multiplier',      '×' . $n($preview['cap_mul'], 2)],
                                ['fa-calculator',     'Theoretical per hour',    $n($preview['hourly_theoretical'])],
                                ['fa-gauge-high',     'Hour cap',                $n($preview['hour_cap'], 0)],
                                ['fa-check',          'Final per hour (capped)', $n($preview['final_hourly'])],
                                ['fa-user-clock',     'Seeding per run',         number_format($preview['avg_hours'] * 60, 0) . ' min'],
                            ] as [$ic, $lbl, $val]): ?>
                            <div class="sb-row"><span class="text-body-secondary"><i class="fa-solid <?= $ic ?> me-2"></i><?= $lbl ?></span><b><?= $val ?></b></div>
                            <?php endforeach; ?>
                            <div class="row g-2 mt-2">
                                <!-- Раньше подпись «Per 15min Run» и «Per Hour = run × 4» были зашиты,
                                     хотя интервал cron настраивается -->
                                <div class="col-4"><div class="sb-big"><div class="sb-muted">Per <?= $cronMin ?>-min run</div><div class="v text-primary"><?= $n($preview['per_run'], 2) ?></div></div></div>
                                <div class="col-4"><div class="sb-big"><div class="sb-muted">Per hour</div><div class="v text-success"><?= $n($preview['real_hourly']) ?></div></div></div>
                                <div class="col-4"><div class="sb-big"><div class="sb-muted">Per day</div><div class="v text-warning"><?= number_format($daily) ?></div></div></div>
                            </div>
                            <div class="sb-muted mt-3"><i class="fa-solid fa-circle-info me-1"></i>Uses the saved values — press Save after changing settings to update this.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="sb-card sb-sec">
                        <div class="sb-sec-head"><span class="sb-sec-icon ic-red"><i class="fa-solid fa-chart-line"></i></span>
                            <div><h3 class="sb-sec-title">Inflation forecast</h3><div class="sb-muted">Points entering the economy</div></div></div>
                        <div class="sb-sec-body">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold" for="inflationUsers"><i class="fa-solid fa-users me-1"></i>Active seeders</label>
                                    <input type="number" class="form-control" id="inflationUsers" value="<?= $activeSeeders ?>" min="1">
                                    <div class="sb-muted mt-1">Now seeding: <?= number_format($activeSeeders) ?></div>
                                </div>
                                <div class="col-6"><div class="sb-big"><div class="sb-muted">Per user / day</div><div class="v" id="inflationAvgBonus" data-daily="<?= (float)$daily ?>"><?= number_format($daily) ?></div></div></div>
                            </div>
                            <div class="row g-2">
                                <div class="col-6"><div class="sb-big"><div class="sb-muted"><i class="fa-solid fa-sun me-1 text-warning"></i>Daily release</div><div class="v" id="inflationDaily"><?= number_format($dailyAll) ?></div></div></div>
                                <div class="col-6"><div class="sb-big"><div class="sb-muted"><i class="fa-solid fa-calendar me-1 text-primary"></i>30-day release</div><div class="v" id="inflationMonthly"><?= number_format($dailyAll * 30) ?></div></div></div>
                                <div class="col-12"><div class="sb-big text-start d-flex align-items-center gap-3">
                                    <i class="fa-solid fa-wallet fa-lg text-info"></i>
                                    <div class="flex-grow-1"><div class="sb-muted">Balance per seeder after 30 days</div><div class="v" id="inflationAvgBalance"><?= number_format($daily * 30) ?></div></div>
                                </div></div>
                            </div>
                            <div class="alert alert-warning d-flex gap-2 mt-3 mb-0 rounded-4 small"><i class="fa-solid fa-triangle-exclamation mt-1"></i><div>High daily release devalues the bonus shop over time — compare with what items cost.</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="sb-card">
                        <div class="sb-sec-head"><span class="sb-sec-icon ic-slate"><i class="fa-solid fa-code"></i></span>
                            <div><h3 class="sb-sec-title">Generated cron config</h3><div class="sb-muted">For reference — the values the cron script uses</div></div>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="copyCodeBtn"><i class="fa-regular fa-copy me-1"></i>Copy</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadCodeBtn"><i class="fa-solid fa-download me-1"></i>Download</button>
                            </div></div>
                        <pre class="sb-code" id="generatedCode"><?= htmlspecialchars($configCode) ?></pre>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->

    <div class="sb-card sb-savebar">
        <span class="sb-muted">
            <span class="badge rounded-pill text-bg-warning me-2" id="sbDirty" hidden><i class="fa-solid fa-pen me-1"></i>Unsaved changes</span>
            <i class="fa-solid fa-circle-info me-1"></i>Changes apply from the next cron run
        </span>
        <button type="button" class="btn btn-primary px-4" id="sbSaveBar" onclick="document.getElementById('saveBtn').click()"><i class="fa-solid fa-floppy-disk me-1"></i>Save settings</button>
    </div>
</div>

<script>
window.seedbonusPresets = <?= json_encode($seedbonus->getPresets()) ?>;
document.addEventListener('DOMContentLoaded', function () {
    // Мастер-выключатель: цвет рамки меняется сразу
    const sw = document.getElementById('systemEnabled'), box = document.getElementById('sbMaster');
    sw && sw.addEventListener('change', () => { box.classList.toggle('on', sw.checked); box.classList.toggle('off', !sw.checked); });
});
</script>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/seedbonus_settings.js?ver=2"></script>

<?php stdfoot(); ?>