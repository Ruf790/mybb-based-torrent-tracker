<?php
declare(strict_types=1);

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
        while ($row = $db->fetch_array($q)) {
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
        $enableHeuristic = $this->getSetting('enable_heuristic', 'on') === 'on';

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

stdhead('Seedbonus System Settings');
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/seedbonus_settings.css">

<div class="container py-5">

    <!-- Header card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div><i class="bi bi-gear me-2"></i>Seedbonus System Settings</div>
            <div>
                <button class="btn btn-sm btn-light me-2" id="saveBtn"><i class="bi bi-save me-1"></i>Save</button>
                <button class="btn btn-sm btn-warning" id="resetBtn"><i class="bi bi-arrow-clockwise me-1"></i>Reset</button>
            </div>
        </div>
        <div class="card-body">

            <!-- Presets -->
            <h6 class="text-muted mb-3"><i class="bi bi-lightning me-2"></i>Quick Presets:</h6>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <?php foreach ($seedbonus->getPresets() as $preset => $_): ?>
                <span class="badge bg-secondary config-badge" data-preset="<?= htmlspecialchars($preset) ?>">
                    <i class="bi bi-shield me-1"></i><?= ucfirst($preset) ?>
                </span>
                <?php endforeach; ?>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <?php foreach (['basic'=>['bi-sliders','Basic'],'multipliers'=>['bi-percent','Multipliers'],'time'=>['bi-clock','Time'],'preview'=>['bi-eye','Preview']] as $id=>[$icon,$label]): ?>
                <li class="nav-item">
                    <button class="nav-link <?= $id === 'basic' ? 'active' : '' ?>"
                            data-bs-toggle="tab" data-bs-target="#<?= $id ?>" type="button">
                        <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content">

                <!-- ── Tab: Basic ──────────────────────────────── -->
                <div class="tab-pane fade show active" id="basic">
                    <form id="basicForm">
                        <div class="row">
						
						
						     <!-- Master Switch -->
<div class="col-12 mb-4">
    <div class="card border-<?= $seedbonus->getSetting('enabled', true) ? 'success' : 'danger' ?>">
        <div class="card-header bg-<?= $seedbonus->getSetting('enabled', true) ? 'success' : 'danger' ?> text-white">
            <i class="bi bi-power me-2"></i>System Master Switch
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Enable or disable the entire seedbonus cron. When disabled, no bonus points are awarded at all, regardless of other settings.</p>
            <div class="form-check form-switch">
                <input type="hidden" name="seedbonus_enabled" value="no">
                <input class="form-check-input" type="checkbox" id="systemEnabled"
                       name="seedbonus_enabled" value="yes"
                       <?= $seedbonus->getSetting('enabled', true) ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold" for="systemEnabled">
                    Seedbonus System Enabled
                </label>
            </div>
        </div>
    </div>
</div>
						
						
						
						
						
						
                            <!-- Base Bonus -->
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header bg-info text-white"><i class="bi bi-cash-coin me-2"></i>Base Bonus</div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-3">Main bonus multiplier. Higher = more points for users.</p>
                                        <label class="form-label">Bonus per hour: <span id="baseBonusValue"><?= $s('base_bonus', 10.0) ?></span> points</label>
                                        <input type="range" class="form-range" id="baseBonus" name="seedbonus_base_bonus"
                                               min="1" max="30" step="0.5" value="<?= $s('base_bonus', 10.0) ?>">
                                        <div class="d-flex justify-content-between text-muted small">
                                            <span>1.0</span><span>Conservative</span><span>15.0</span><span>Generous</span><span>30.0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hour Cap -->
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header bg-warning text-white"><i class="bi bi-speedometer me-2"></i>Hour Cap</div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-3">Maximum bonus per hour per user. Abuse protection.</p>
                                        <label class="form-label">Max per hour: <span id="hourCapValue"><?= $s('hour_cap', 500.0) ?></span> points</label>
                                        <input type="range" class="form-range" id="hourCap" name="seedbonus_hour_cap"
                                               min="100" max="5000" step="50" value="<?= $s('hour_cap', 500.0) ?>">
                                        <div class="d-flex justify-content-between text-muted small">
                                            <span>100</span><span>Strict</span><span>1000</span><span>Generous</span><span>5000</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Torrent Multiplier Type -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-success text-white"><i class="bi bi-collection me-2"></i>Torrent Count Multiplier</div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">How the number of seeded torrents affects the bonus.</p>
                                        <div class="row">
                                            <?php
                                            $types = [
                                                'penalty' => ['Penalty for Many', '1-20: 100%<br>21-50: 90%<br>51-100: 80%<br>100+: 70%'],
                                                'neutral' => ['Neutral',           '1-100: 100%<br>101+: 90%'],
                                                'reward'  => ['Reward for Many',   '1-19: 90%<br>20-49: 100%<br>50-99: 110%<br>100+: 120%'],
                                                'flat'    => ['Fixed',             'Always: '],
                                            ];
                                            $current = $seedbonus->getSetting('torrent_multiplier_type');
                                            foreach ($types as $type => [$label, $desc]):
                                            ?>
                                            <div class="col-md-3 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                           name="seedbonus_torrent_multiplier_type"
                                                           id="mult<?= ucfirst($type) ?>" value="<?= $type ?>"
                                                           <?= $current === $type ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="mult<?= ucfirst($type) ?>">
                                                        <strong><?= $label ?></strong>
                                                    </label>
                                                    <div class="text-muted small">
                                                        <?= $desc ?>
                                                        <?php if ($type === 'flat'): ?>
                                                        <input type="number" class="form-control form-control-sm d-inline w-50"
                                                               name="seedbonus_flat_multiplier"
                                                               value="<?= $s('flat_multiplier', 1.0) ?>"
                                                               step="0.1" min="0.1" max="2.0"> ×100%
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ── Tab: Multipliers ────────────────────────── -->
                <div class="tab-pane fade" id="multipliers">
                    <form id="multipliersForm">
                        <div class="row">
                            <!-- Leecher -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-danger text-white"><i class="bi bi-download me-2"></i>Leecher Multipliers</div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-3">Encourage seeding torrents with leechers.</p>
                                        <?php foreach (['leech_none'=>'No leechers','leech_few'=>'1-2 leechers','leech_many'=>'3+ leechers'] as $k=>$lbl): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><?= $lbl ?>:</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="seedbonus_<?= $k ?>"
                                                       value="<?= $s($k, 1.2) ?>" step="0.1" min="0.5" max="3.0">
                                                <span class="input-group-text">×</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Size -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white"><i class="bi bi-hdd me-2"></i>Size Multipliers</div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-3">Encourage seeding large files.</p>
                                        <?php foreach (['small'=>'&lt; 0.5 GB','medium'=>'&lt; 2 GB','large'=>'&lt; 8 GB','xlarge'=>'&lt; 20 GB','huge'=>'≥ 20 GB'] as $k=>$lbl): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><?= $lbl ?>:</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="seedbonus_size_<?= $k ?>"
                                                       value="<?= $s("size_$k", 1.0) ?>" step="0.1" min="0.5" max="3.0">
                                                <span class="input-group-text">×</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white"><i class="bi bi-star me-2"></i>Additional Multipliers</div>
                                    <div class="card-body">
                                        <h6 class="text-muted mb-2">Many Seeders Penalty:</h6>
                                        <?php foreach (['seeders_many'=>['&gt;100 seeders',0.9],'seeders_medium'=>['&gt;50 seeders',0.95]] as $k=>[$lbl,$def]): ?>
                                        <div class="mb-2">
                                            <label class="form-label small"><?= $lbl ?>:</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="seedbonus_<?= $k ?>"
                                                       value="<?= $s($k, $def) ?>" step="0.05" min="0.1" max="1.0">
                                                <span class="input-group-text">×</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>

                                        <h6 class="text-muted mb-2 mt-3">Age Bonus:</h6>
                                        <?php foreach (['age_old'=>['&gt;180 days',1.5],'age_medium'=>['&gt;60 days',1.3]] as $k=>[$lbl,$def]): ?>
                                        <div class="mb-2">
                                            <label class="form-label small"><?= $lbl ?>:</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="seedbonus_<?= $k ?>"
                                                       value="<?= $s($k, $def) ?>" step="0.1" min="1.0" max="3.0">
                                                <span class="input-group-text">×</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>

                                        <h6 class="text-muted mb-2 mt-3">Promo Torrents:</h6>
                                        <?php foreach (['promo_free'=>['Freeleech',0.7],'promo_silver'=>['Silver (50%)',0.5],'promo_double'=>['Double Upload',0.5]] as $k=>[$lbl,$def]): ?>
                                        <div class="mb-2">
                                            <label class="form-label small"><?= $lbl ?>:</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="seedbonus_<?= $k ?>"
                                                       value="<?= $s($k, $def) ?>" step="0.1" min="0" max="2.0">
                                                <span class="input-group-text">+ bonus</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ── Tab: Time ───────────────────────────────── -->
                <div class="tab-pane fade" id="time">
                    <form id="timeForm">
                        <div class="row">
                            <!-- Intervals -->
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header bg-info text-white"><i class="bi bi-clock-history me-2"></i>Intervals</div>
                                    <div class="card-body">
                                        <?php foreach ([
    ['cronInterval',     'seedbonus_cron_interval',     'Cron interval',     'cronIntervalValue',     5, 60, 5, 15, ['5 min','Frequent','30 min','Rare','60 min']],
    ['announceInterval', 'seedbonus_announce_interval', 'Announce interval', 'announceIntervalValue', 5, 60, 5, 15, ['5 min','15 min','30 min','60 min']],
    ['historyDays',      'seedbonus_history_days',      'Activity history',  'historyDaysValue',      1, 30, 1, 1,  ['1','15','30']],
] as [$id, $name, $lbl, $valId, $min, $max, $step, $def, $marks]):
    $val = $s(substr($name, 10), $def);
?>
                                        <div class="mb-4">
                                            <label class="form-label"><?= $lbl ?>: <span id="<?= $valId ?>"><?= $val ?></span> <?= $id === 'historyDays' ? 'days' : 'minutes' ?></label>
                                            <input type="range" class="form-range" id="<?= $id ?>" name="<?= $name ?>"
                                                   min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= $val ?>">
                                            <div class="d-flex justify-content-between text-muted small">
                                                <?php foreach ($marks as $m): ?><span><?= $m ?></span><?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Heuristic -->
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header bg-warning text-white"><i class="bi bi-person-workspace me-2"></i>Seeding Time Heuristic</div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-3">Automatic estimation of seeding time based on torrent count.</p>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableHeuristic"
                                                   name="seedbonus_enable_heuristic"
                                                   <?= $seedbonus->getSetting('enable_heuristic', true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableHeuristic">Use heuristic (recommended)</label>
                                        </div>
                                        <table class="table table-sm">
                                            <thead><tr><th>Torrents</th><th>Assumed Time</th><th>Setting</th></tr></thead>
                                            <tbody>
                                            <?php foreach (['50'=>'≥ 50','40'=>'≥ 40','30'=>'≥ 30','20'=>'≥ 20','10'=>'≥ 10','5'=>'≥ 5','1'=>'1-4'] as $k=>$lbl): ?>
                                            <tr>
                                                <td><?= $lbl ?></td>
                                                <td><?= $s("heuristic_$k", 24.0) ?> h/day</td>
                                                <td><input type="number" class="form-control form-control-sm"
                                                           name="seedbonus_heuristic_<?= $k ?>"
                                                           value="<?= $s("heuristic_$k", 24.0) ?>"
                                                           step="1" min="1" max="24"></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ── Tab: Preview ────────────────────────────── -->
                <div class="tab-pane fade" id="preview">
                    <div class="row">
                        <!-- Test Calculation -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-lg h-100">
                                <div class="card-header bg-white border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                            <i class="bi bi-calculator-fill text-primary fs-5"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 fw-bold">Test Calculation</h5>
                                            <small class="text-muted">Simulated user with 42 torrents</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3 mb-4">
                                        <div class="col-6">
                                            <div class="text-center p-3 rounded-3 bg-white shadow-sm">
                                                <div class="text-muted small mb-1"><i class="bi bi-hdd-stack me-1"></i>Torrents</div>
                                                <div class="display-6 fw-bold text-primary"><?= $preview['torrents'] ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center p-3 rounded-3 bg-white shadow-sm">
                                                <div class="text-muted small mb-1"><i class="bi bi-gem me-1"></i>Raw Bonus</div>
                                                <div class="display-6 fw-bold text-success"><?= $n($preview['raw_bonus']) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card border-0 bg-white shadow-sm">
                                        <div class="card-body p-4">
                                            <h6 class="fw-bold mb-3"><i class="bi bi-calculator me-2"></i>Calculation Breakdown</h6>
                                            <div class="row g-3">
                                                <div class="col-6">
                                                    <?php foreach ([
                                                        ['Base Rate',         'text-dark',    $n($preview['base_bonus'])],
                                                        ['Torrent Multiplier','text-warning', '×' . $n($preview['cap_mul'], 2)],
                                                        ['Avg Seeding Time',  'text-info',    number_format($preview['avg_hours'] * 60, 0) . ' min'],
                                                    ] as [$lbl, $cls, $val]): ?>
                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                        <div class="text-muted small"><?= $lbl ?></div>
                                                        <div class="fw-bold <?= $cls ?>"><?= $val ?></div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="col-6">
                                                    <?php foreach ([
                                                        ['Hour Cap',           'text-danger',  $n($preview['hour_cap'])],
                                                        ['Theoretical Hourly', 'text-primary', $n($preview['hourly_theoretical'])],
                                                        ['Final Hourly',       'text-success', $n($preview['final_hourly'])],
                                                    ] as [$lbl, $cls, $val]): ?>
                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                        <div class="text-muted small"><?= $lbl ?></div>
                                                        <div class="fw-bold <?= $cls ?>"><?= $val ?></div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="row g-3 mt-3">
                                                <?php foreach ([
                                                    ['Per 15min Run','primary', $n($preview['per_run'])],
                                                    ['Per Hour',     'success', $n($preview['per_run'] * 4)],
                                                    ['Daily Total',  'warning', number_format($preview['daily'])],
                                                ] as [$lbl, $color, $val]): ?>
                                                <div class="col-4">
                                                    <div class="text-center p-3 rounded-3 bg-<?= $color ?> bg-opacity-10 border border-<?= $color ?> border-opacity-25">
                                                        <div class="text-muted small mb-1"><?= $lbl ?></div>
                                                        <div class="h4 fw-bold text-<?= $color ?> mb-0"><?= $val ?></div>
                                                        <small class="text-muted">points</small>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="alert alert-info border-0 mt-4 small">
                                                <i class="bi bi-info-circle-fill text-info me-2"></i>
                                                <strong>Note:</strong> Simulated calculation using optimized torrent mix. Real values may vary.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inflation Forecast -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-lg h-100">
                                <div class="card-header bg-transparent border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-dark bg-opacity-25 p-2 rounded-circle me-3">
                                            <i class="bi bi-graph-up-arrow text-dark"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 fw-bold">Inflation Forecast</h5>
                                            <small class="text-muted">System-wide points emission</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3 mb-4">
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-dark bg-opacity-10">
                                                <label class="form-label small mb-1"><i class="bi bi-people me-1"></i>Active Users</label>
                                                <input type="number" class="form-control" id="inflationUsers" value="100" min="1">
                                                <div class="text-muted small mt-1">Adjust for simulation</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-dark bg-opacity-10">
                                                <div class="text-muted small mb-1"><i class="bi bi-cash-coin me-1"></i>Avg User Bonus</div>
                                                <div class="display-6 fw-bold" id="inflationAvgBonus"><?= number_format($preview['daily']) ?></div>
                                                <div class="text-muted small">points/day</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <?php foreach ([
                                            ['Daily Release',   'sun',           'success', 'inflationDaily',   $preview['daily'] * 100,      'Points entering system daily'],
                                            ['Monthly Release', 'calendar-month','warning', 'inflationMonthly', $preview['daily'] * 100 * 30, '30-day projection'],
                                        ] as [$lbl, $icon, $color, $id, $val, $desc]): ?>
                                        <div class="col-6">
                                            <div class="p-4 rounded-3 bg-dark bg-opacity-10 border border-dark border-opacity-25">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="bg-<?= $color ?> bg-opacity-25 p-2 rounded-circle me-3">
                                                        <i class="bi bi-<?= $icon ?> text-dark fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-muted small"><?= $lbl ?></div>
                                                        <div class="h3 fw-bold mb-0" id="<?= $id ?>"><?= number_format($val) ?></div>
                                                    </div>
                                                </div>
                                                <div class="text-muted small"><?= $desc ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>

                                        <div class="col-12">
                                            <div class="p-4 rounded-3 bg-dark bg-opacity-10 border border-dark border-opacity-25">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-info bg-opacity-25 p-2 rounded-circle me-3">
                                                        <i class="bi bi-pie-chart text-dark fs-5"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="text-muted small">Average User Balance</div>
                                                        <div class="h2 fw-bold mb-0" id="inflationAvgBalance">
                                                            <?= number_format(($preview['daily'] * 100 * 30) / 500) ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="text-muted small">Based on</div>
                                                        <div class="h5 fw-bold">500 users</div>
                                                    </div>
                                                </div>
                                                <div class="progress" style="height:8px">
                                                    <div class="progress-bar bg-info" style="width:<?= min(100, (($preview['daily'] * 100 * 30) / 500) / 1000 * 100) ?>%"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-2">
                                                    <small class="text-muted">Low</small>
                                                    <small class="text-muted">Average Balance</small>
                                                    <small class="text-muted">High</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-warning border-0 mt-4 small">
                                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                                        <strong>Monitor closely:</strong> High daily release may cause inflation.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-content -->
        </div><!-- /card-body -->
    </div><!-- /card -->
</div><!-- /container -->

<script>window.seedbonusPresets = <?= json_encode($seedbonus->getPresets()) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/seedbonus_settings.js"></script>

<?php stdfoot(); ?>