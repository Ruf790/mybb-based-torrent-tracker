<?php
declare(strict_types=1);



class SeedbonusSettings
{
    private array $settings = [];
    private array $presets = [];
    
    public function __construct()
    {
        global $db;
        $this->loadSettings();
        $this->initializePresets();
    }
    
    private function loadSettings(): void
    {
        global $db;
        
        $query = "SELECT setting_key, setting_value, setting_type FROM seedbonus_settings";
        $result = $db->sql_query($query);
        
        if ($result && $db->num_rows($result) > 0) {
            while ($row = $db->fetch_array($result)) {
                $this->settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
            }
        }
    }
    
    private function castValue(mixed $value, string $type): mixed
    {
        return match($type) {
            'boolean' => $value === 'yes' || $value === 'true' || $value === '1',
            'integer' => (int)$value,
            'float'   => (float)$value,
            'array'   => json_decode($value, true) ?? [],
            default   => (string)$value
        };
    }
    
    private function prepareValue(mixed $value, string $type): string
    {
        return match($type) {
            'boolean' => $value ? 'yes' : 'no',
            'integer', 'float' => (string)$value,
            'array'   => json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => (string)$value
        };
    }
    
    private function initializePresets(): void
    {
        $this->presets = [
            'conservative' => [
                'base_bonus' => 5.0,
                'hour_cap' => 250.0,
                'torrent_multiplier_type' => 'penalty',
                'flat_multiplier' => 1.0,
                'leech_none' => 1.0,
                'leech_few' => 1.2,
                'leech_many' => 1.5,
                'size_small' => 0.8,
                'size_medium' => 1.0,
                'size_large' => 1.2,
                'size_xlarge' => 1.3,
                'size_huge' => 1.5,
                'seeders_many' => 0.7,
                'seeders_medium' => 0.85,
                'age_old' => 1.2,
                'age_medium' => 1.1,
                'promo_free' => 0.3,
                'promo_silver' => 0.2,
                'promo_double' => 0.2
            ],
            'balanced' => [
                'base_bonus' => 10.0,
                'hour_cap' => 500.0,
                'torrent_multiplier_type' => 'penalty',
                'flat_multiplier' => 1.0,
                'leech_none' => 1.2,
                'leech_few' => 1.5,
                'leech_many' => 1.8,
                'size_small' => 1.0,
                'size_medium' => 1.2,
                'size_large' => 1.5,
                'size_xlarge' => 1.8,
                'size_huge' => 2.0,
                'seeders_many' => 0.9,
                'seeders_medium' => 0.95,
                'age_old' => 1.5,
                'age_medium' => 1.3,
                'promo_free' => 0.7,
                'promo_silver' => 0.5,
                'promo_double' => 0.5
            ],
            'generous' => [
                'base_bonus' => 15.0,
                'hour_cap' => 1000.0,
                'torrent_multiplier_type' => 'reward',
                'flat_multiplier' => 1.0,
                'leech_none' => 1.5,
                'leech_few' => 1.8,
                'leech_many' => 2.2,
                'size_small' => 1.2,
                'size_medium' => 1.5,
                'size_large' => 1.8,
                'size_xlarge' => 2.0,
                'size_huge' => 2.5,
                'seeders_many' => 0.95,
                'seeders_medium' => 1.0,
                'age_old' => 1.8,
                'age_medium' => 1.5,
                'promo_free' => 1.0,
                'promo_silver' => 0.7,
                'promo_double' => 0.7
            ],
            'avistaz' => [
                'base_bonus' => 12.0,
                'hour_cap' => 750.0,
                'torrent_multiplier_type' => 'reward',
                'flat_multiplier' => 1.0,
                'leech_none' => 1.8,
                'leech_few' => 2.0,
                'leech_many' => 2.5,
                'size_small' => 1.5,
                'size_medium' => 1.8,
                'size_large' => 2.0,
                'size_xlarge' => 2.2,
                'size_huge' => 2.5,
                'seeders_many' => 1.0,
                'seeders_medium' => 1.0,
                'age_old' => 2.0,
                'age_medium' => 1.5,
                'promo_free' => 1.2,
                'promo_silver' => 0.8,
                'promo_double' => 0.8
            ],
            'maximum' => [
                'base_bonus' => 20.0,
                'hour_cap' => 2000.0,
                'torrent_multiplier_type' => 'reward',
                'flat_multiplier' => 1.0,
                'leech_none' => 2.0,
                'leech_few' => 2.5,
                'leech_many' => 3.0,
                'size_small' => 1.8,
                'size_medium' => 2.0,
                'size_large' => 2.2,
                'size_xlarge' => 2.5,
                'size_huge' => 3.0,
                'seeders_many' => 1.0,
                'seeders_medium' => 1.0,
                'age_old' => 2.5,
                'age_medium' => 2.0,
                'promo_free' => 1.5,
                'promo_silver' => 1.0,
                'promo_double' => 1.0
            ]
        ];
    }
    
    public function saveSetting(string $key, mixed $value, string $type = 'string'): bool
    {
        global $db;
        
        $value = $this->prepareValue($value, $type);
        
        // Проверяем существует ли уже настройка
        $check = $db->sql_query("SELECT setting_key FROM seedbonus_settings WHERE setting_key = '" . $db->escape_string($key) . "'");
        
        if ($db->num_rows($check) > 0) {
            // Обновляем существующую настройку
            $query = "UPDATE seedbonus_settings SET 
                setting_value = '" . $db->escape_string($value) . "', 
                setting_type = '" . $db->escape_string($type) . "', 
                updated_at = CURRENT_TIMESTAMP 
                WHERE setting_key = '" . $db->escape_string($key) . "'";
        } else {
            // Вставляем новую настройку
            $query = "INSERT INTO seedbonus_settings (setting_key, setting_value, setting_type) 
                VALUES ('" . $db->escape_string($key) . "', '" . $db->escape_string($value) . "', '" . $db->escape_string($type) . "')";
        }
        
        $result = $db->sql_query($query);
        
        if ($result) {
            $this->settings[$key] = $this->castValue($value, $type);
        }
        
        return (bool)$result;
    }
    
    public function loadPreset(string $presetName): bool
    {
        if (!isset($this->presets[$presetName])) {
            return false;
        }
        
        foreach ($this->presets[$presetName] as $key => $value) {
            $type = is_float($value) ? 'float' : (is_int($value) ? 'integer' : 'string');
            $this->saveSetting($key, $value, $type);
        }
        
        return true;
    }
    
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
    
    public function getAllSettings(): array
    {
        return $this->settings;
    }
    
    public function getPreset(string $name): ?array
    {
        return $this->presets[$name] ?? null;
    }
    
    public function getPresets(): array
    {
        return $this->presets;
    }
    
    
	
	public function calculatePreview(): array
{
    // 1. Берем ВСЕ настройки из БД
    $baseBonus = $this->getSetting('base_bonus', 2.5);          // из БД: 2.5
    $hourCap = $this->getSetting('hour_cap', 250);             // из БД: 250
    $multiplierType = $this->getSetting('torrent_multiplier_type', 'penalty'); // penalty
    $cronInterval = $this->getSetting('cron_interval', 15);    // 15 минут
    $enableHeuristic = $this->getSetting('enable_heuristic', 'on') === 'on'; // включено
    
    // 2. Тестовые данные (42 торрента, как в скриншоте)
    $testTorrents = 42;
    $testRawBonus = 95.1;
    
    // 3. Расчет множителя торрентов
    $capMul = $this->calculateTorrentMultiplier($testTorrents, $multiplierType);
    
    // 4. Теоретический часовой бонус
    $hourlyTheoretical = $testRawBonus * $baseBonus * $capMul;
    
    // 5. С учетом капа
    $finalHourly = min($hourlyTheoretical, $hourCap);
    
    // 6. Расчет времени сидирования за интервал
    $avgHoursPerInterval = $this->calculateSeedingHours($testTorrents, $cronInterval, $enableHeuristic);
    
    // 7. Бонус за ОДИН запуск крона (15 минут)
    $perRun = $finalHourly * $avgHoursPerInterval;
    
    // 8. Реальный часовой бонус
    $runsPerHour = 60 / $cronInterval; // 4 при cron_interval=15
    $realHourly = $perRun * $runsPerHour;
    
    // 9. Суточная проекция
    $daily = $realHourly * 24;
    
    return [
        'torrents' => $testTorrents,
        'raw_bonus' => $testRawBonus,
        'base_bonus' => $baseBonus,
        'cap_mul' => $capMul,
        'hourly_theoretical' => round($hourlyTheoretical, 1),
        'hour_cap' => $hourCap,
        'avg_hours' => round($avgHoursPerInterval, 3), // часы за ОДИН интервал
        'final_hourly' => round($finalHourly, 1),      // теоретический часовой (после капа)
        'real_hourly' => round($realHourly, 1),        // реальный часовой с учетом времени
        'per_run' => round($perRun, 2),
        'daily' => round($daily, 0)
    ];
}

private function calculateTorrentMultiplier(int $torrents, string $type): float
{
    $flatMultiplier = (float)$this->getSetting('flat_multiplier', 1.0);
    
    switch ($type) {
        case 'penalty':
            if ($torrents <= 20) return 1.0;
            if ($torrents <= 50) return 0.9;
            if ($torrents <= 100) return 0.8;
            return 0.7;
        case 'neutral':
            return ($torrents <= 100) ? 1.0 : 0.9;
        case 'reward':
            if ($torrents >= 100) return 1.2;
            if ($torrents >= 50) return 1.1;
            if ($torrents >= 20) return 1.0;
            return 0.9;
        case 'flat':
            return $flatMultiplier;
        default:
            return 1.0;
    }
}

private function calculateSeedingHours(int $torrents, int $cronInterval, bool $enableHeuristic): float
{
    // Максимальное время за интервал (15 мин = 0.25 часа)
    $maxHoursPerInterval = $cronInterval / 60;
    
    if ($enableHeuristic) {
        // Эвристика дает часы В ДЕНЬ
        $heuristicHoursPerDay = $this->getHeuristicHours($torrents);
        // Переводим дневные часы в часы за интервал
        $hoursPerInterval = $heuristicHoursPerDay * ($cronInterval / 60 / 24);
    } else {
        // Без эвристики - максимальное время
        $hoursPerInterval = $maxHoursPerInterval;
    }
    
    // Не может превышать максимальное время интервала
    return min($hoursPerInterval, $maxHoursPerInterval);
}

private function getHeuristicHours(int $torrents): float
{
    // Берем реальные настройки из БД
    if ($torrents >= 50) return (float)$this->getSetting('heuristic_50', 24);
    if ($torrents >= 40) return (float)$this->getSetting('heuristic_40', 20);
    if ($torrents >= 30) return (float)$this->getSetting('heuristic_30', 16);
    if ($torrents >= 20) return (float)$this->getSetting('heuristic_20', 12);
    if ($torrents >= 10) return (float)$this->getSetting('heuristic_10', 8);
    if ($torrents >= 5) return (float)$this->getSetting('heuristic_5', 4);
    return (float)$this->getSetting('heuristic_1', 2);
}
	
	
	
	
    
    public function generateConfigCode(): string
    {
        $baseBonus = $this->getSetting('base_bonus', 10.0);
        $hourCap = $this->getSetting('hour_cap', 500.0);
        $cronInterval = $this->getSetting('cron_interval', 15) * 60;
        $announceInterval = $this->getSetting('announce_interval', 15) * 60;
        
        $multiplierType = $this->getSetting('torrent_multiplier_type', 'penalty');
        $torrentMultiplierCode = match($multiplierType) {
            'penalty' => "if (\$user_torrents <= 20) {
    \$user_cap_mul = 1.0;
} elseif (\$user_torrents <= 50) {
    \$user_cap_mul = 0.9;
} elseif (\$user_torrents <= 100) {
    \$user_cap_mul = 0.8;
} else {
    \$user_cap_mul = 0.7;
}",
            'neutral' => "if (\$user_torrents <= 100) {
    \$user_cap_mul = 1.0;
} else {
    \$user_cap_mul = 0.9;
}",
            'reward' => "if (\$user_torrents >= 100) {
    \$user_cap_mul = 1.2;
} elseif (\$user_torrents >= 50) {
    \$user_cap_mul = 1.1;
} elseif (\$user_torrents >= 20) {
    \$user_cap_mul = 1.0;
} else {
    \$user_cap_mul = 0.9;
}",
            'flat' => sprintf("\$user_cap_mul = %.1f;", $this->getSetting('flat_multiplier', 1.0)),
            default => "\$user_cap_mul = 1.0;"
        };
        
        return <<<PHP
// ===== SEEDBONUS CRON SETTINGS =====
\$ANNOUNCE_INTERVAL = {$announceInterval};
\$CRON_INTERVAL_SEC = {$cronInterval};
\$BASE_BONUS = {$baseBonus};
\$HOUR_CAP = {$hourCap};
\$MAX_DB_VALUE = 9999999.9;
\$BATCH_SIZE = 100;

// Torrent count multiplier
{$torrentMultiplierCode}

// Seeding time heuristic
if (\$user_torrents >= 50) {
    \$user_avg_hours = {$this->getSetting('heuristic_50', 24.0)};
} elseif (\$user_torrents >= 40) {
    \$user_avg_hours = {$this->getSetting('heuristic_40', 20.0)};
} elseif (\$user_torrents >= 30) {
    \$user_avg_hours = {$this->getSetting('heuristic_30', 16.0)};
} elseif (\$user_torrents >= 20) {
    \$user_avg_hours = {$this->getSetting('heuristic_20', 12.0)};
} elseif (\$user_torrents >= 10) {
    \$user_avg_hours = {$this->getSetting('heuristic_10', 8.0)};
} elseif (\$user_torrents >= 5) {
    \$user_avg_hours = {$this->getSetting('heuristic_5', 4.0)};
} else {
    \$user_avg_hours = {$this->getSetting('heuristic_1', 2.0)};
}
PHP;
    }
}

// Инициализация
$seedbonus = new SeedbonusSettings();

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save':
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'seedbonus_') === 0) {
                    $cleanKey = substr($key, 10); // Убираем префикс
                    $type = match(true) {
                        is_numeric($value) && strpos($value, '.') !== false => 'float',
                        is_numeric($value) => 'integer',
                        $value === 'yes' || $value === 'no' => 'boolean',
                        default => 'string'
                    };
                    $seedbonus->saveSetting($cleanKey, $value, $type);
                }
            }
            $response = ['success' => true, 'message' => 'Settings saved successfully'];
            break;
            
        case 'load_preset':
            $preset = $_POST['preset'] ?? '';
            if ($seedbonus->loadPreset($preset)) {
                $response = ['success' => true, 'message' => "Preset '{$preset}' loaded"];
            } else {
                $response = ['success' => false, 'message' => 'Invalid preset'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Получаем данные для отображения
$preview = $seedbonus->calculatePreview();
$configCode = $seedbonus->generateConfigCode();

// HTML шапка
stdhead("Seedbonus System Settings");
?>

<div class="container py-5">
    <div id="notification"></div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-gear me-2"></i>
                        Seedbonus System Settings
                    </div>
                    <div>
                        <button class="btn btn-sm btn-light me-2" id="saveBtn">
                            <i class="bi bi-save me-1"></i>Save
                        </button>
                        <button class="btn btn-sm btn-warning" id="resetBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Quick Presets -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-muted mb-3">
                                <i class="bi bi-lightning me-2"></i>Quick Presets:
                            </h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($seedbonus->getPresets() as $preset => $config): ?>
                                    <span class="badge bg-secondary config-badge" data-preset="<?= htmlspecialchars($preset) ?>">
                                        <i class="bi bi-shield me-1"></i><?= ucfirst($preset) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tabs -->
                    <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">
                                <i class="bi bi-sliders me-1"></i>Basic
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="multipliers-tab" data-bs-toggle="tab" data-bs-target="#multipliers" type="button">
                                <i class="bi bi-percent me-1"></i>Multipliers
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="time-tab" data-bs-toggle="tab" data-bs-target="#time" type="button">
                                <i class="bi bi-clock me-1"></i>Time
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview" type="button">
                                <i class="bi bi-eye me-1"></i>Preview
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="configTabsContent">
                        <!-- Tab 1: Basic Settings -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <form id="basicForm">
                                <div class="row">
                                    <!-- Base Bonus -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-info text-white">
                                                <i class="bi bi-cash-coin me-2"></i>Base Bonus
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted small mb-3">
                                                    Main bonus multiplier. Higher = more points for users.
                                                </p>
                                                <div class="mb-3">
                                                    <label for="baseBonus" class="form-label">
                                                        Bonus per hour: <span class="slider-value" id="baseBonusValue"><?= htmlspecialchars((string)$seedbonus->getSetting('base_bonus', 10.0)) ?></span> points
                                                    </label>
                                                    <input type="range" class="form-range" id="baseBonus" name="seedbonus_base_bonus" 
                                                           min="1" max="30" step="0.5" value="<?= htmlspecialchars((string)$seedbonus->getSetting('base_bonus', 10.0)) ?>">
                                                    <div class="d-flex justify-content-between text-muted small">
                                                        <span>1.0</span>
                                                        <span>Conservative</span>
                                                        <span>15.0</span>
                                                        <span>Generous</span>
                                                        <span>30.0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hour Cap -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-warning text-white">
                                                <i class="bi bi-speedometer me-2"></i>Hour Cap
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted small mb-3">
                                                    Maximum bonus per hour per user. Abuse protection.
                                                </p>
                                                <div class="mb-3">
                                                    <label for="hourCap" class="form-label">
                                                        Max per hour: <span class="slider-value" id="hourCapValue"><?= htmlspecialchars((string)$seedbonus->getSetting('hour_cap', 500.0)) ?></span> points
                                                    </label>
                                                    <input type="range" class="form-range" id="hourCap" name="seedbonus_hour_cap"
                                                           min="100" max="5000" step="50" value="<?= htmlspecialchars((string)$seedbonus->getSetting('hour_cap', 500.0)) ?>">
                                                    <div class="d-flex justify-content-between text-muted small">
                                                        <span>100</span>
                                                        <span>Strict</span>
                                                        <span>1000</span>
                                                        <span>Generous</span>
                                                        <span>5000</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Torrent Multiplier -->
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header bg-success text-white">
                                                <i class="bi bi-collection me-2"></i>Torrent Count Multiplier
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted mb-3">
                                                    How the number of seeded torrents affects the bonus.
                                                </p>
                                                
                                                <div class="row">
                                                    <?php
                                                    $types = [
                                                        'penalty' => ['label' => 'Penalty for Many', 'desc' => '1-20: 100%<br>21-50: 90%<br>51-100: 80%<br>100+: 70%'],
                                                        'neutral' => ['label' => 'Neutral', 'desc' => '1-100: 100%<br>101+: 90%'],
                                                        'reward' => ['label' => 'Reward for Many', 'desc' => '1-19: 90%<br>20-49: 100%<br>50-99: 110%<br>100+: 120%'],
                                                        'flat' => ['label' => 'Fixed', 'desc' => 'Always: ']
                                                    ];
                                                    ?>
                                                    
                                                    <?php foreach ($types as $type => $info): ?>
                                                    <div class="col-md-3 mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="seedbonus_torrent_multiplier_type" 
                                                                   id="mult<?= ucfirst($type) ?>" value="<?= htmlspecialchars($type) ?>" 
                                                                   <?= $seedbonus->getSetting('torrent_multiplier_type') === $type ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="mult<?= ucfirst($type) ?>">
                                                                <strong><?= htmlspecialchars($info['label']) ?></strong>
                                                            </label>
                                                            <div class="text-muted small">
                                                                <?= $info['desc'] ?>
                                                                <?php if ($type === 'flat'): ?>
                                                                    <input type="number" class="form-control form-control-sm d-inline w-50" 
                                                                           name="seedbonus_flat_multiplier" value="<?= htmlspecialchars((string)$seedbonus->getSetting('flat_multiplier', 1.0)) ?>" 
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

                        <!-- Tab 2: Multipliers -->
                        <div class="tab-pane fade" id="multipliers" role="tabpanel">
                            <form id="multipliersForm">
                                <div class="row">
                                    <!-- Leecher Multipliers -->
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-danger text-white">
                                                <i class="bi bi-download me-2"></i>Leecher Multipliers
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted small mb-3">
                                                    Encourage seeding torrents with leechers.
                                                </p>
                                                <div class="mb-3">
                                                    <label class="form-label">No leechers:</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="seedbonus_leech_none" 
                                                               value="<?= htmlspecialchars((string)$seedbonus->getSetting('leech_none', 1.2)) ?>" step="0.1" min="0.5" max="3.0">
                                                        <span class="input-group-text">×</span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">1-2 leechers:</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="seedbonus_leech_few" 
                                                               value="<?= htmlspecialchars((string)$seedbonus->getSetting('leech_few', 1.5)) ?>" step="0.1" min="0.5" max="3.0">
                                                        <span class="input-group-text">×</span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">3+ leechers:</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="seedbonus_leech_many" 
                                                               value="<?= htmlspecialchars((string)$seedbonus->getSetting('leech_many', 1.8)) ?>" step="0.1" min="0.5" max="3.0">
                                                        <span class="input-group-text">×</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Size Multipliers -->
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-primary text-white">
                                                <i class="bi bi-hdd me-2"></i>Size Multipliers
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted small mb-3">
                                                    Encourage seeding large files.
                                                </p>
                                                <?php
                                                $sizes = [
                                                    'small' => '&lt; 0.5 GB',
                                                    'medium' => '&lt; 2 GB',
                                                    'large' => '&lt; 8 GB',
                                                    'xlarge' => '&lt; 20 GB',
                                                    'huge' => '≥ 20 GB'
                                                ];
                                                ?>
                                                
                                                <?php foreach ($sizes as $key => $label): ?>
                                                <div class="mb-3">
                                                    <label class="form-label"><?= htmlspecialchars($label) ?>:</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="seedbonus_size_<?= htmlspecialchars($key) ?>" 
                                                               value="<?= htmlspecialchars((string)$seedbonus->getSetting("size_$key", 1.0)) ?>" step="0.1" min="0.5" max="3.0">
                                                        <span class="input-group-text">×</span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Other Multipliers -->
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-success text-white">
                                                <i class="bi bi-star me-2"></i>Additional Multipliers
                                            </div>
                                            <div class="card-body">
                                                <!-- Many Seeders Penalty -->
                                                <div class="mb-4">
                                                    <h6 class="text-muted mb-2">Many Seeders Penalty:</h6>
                                                    <div class="mb-2">
                                                        <label class="form-label small">&gt;100 seeders:</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_seeders_many" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('seeders_many', 0.9)) ?>" step="0.05" min="0.1" max="1.0">
                                                            <span class="input-group-text">×</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">&gt;50 seeders:</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_seeders_medium" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('seeders_medium', 0.95)) ?>" step="0.05" min="0.1" max="1.0">
                                                            <span class="input-group-text">×</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Age Bonus -->
                                                <div class="mb-4">
                                                    <h6 class="text-muted mb-2">Age Bonus:</h6>
                                                    <div class="mb-2">
                                                        <label class="form-label small">&gt;180 days:</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_age_old" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('age_old', 1.5)) ?>" step="0.1" min="1.0" max="3.0">
                                                            <span class="input-group-text">×</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">&gt;60 days:</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_age_medium" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('age_medium', 1.3)) ?>" step="0.1" min="1.0" max="3.0">
                                                            <span class="input-group-text">×</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Promo Multipliers -->
                                                <div class="mb-4">
                                                    <h6 class="text-muted mb-2">Promo Torrents:</h6>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Freeleech:</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_promo_free" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('promo_free', 0.7)) ?>" step="0.1" min="0" max="2.0">
                                                            <span class="input-group-text">+ bonus</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Silver (50%):</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_promo_silver" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('promo_silver', 0.5)) ?>" step="0.1" min="0" max="2.0">
                                                            <span class="input-group-text">+ bonus</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Double Upload:</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="seedbonus_promo_double" 
                                                                   value="<?= htmlspecialchars((string)$seedbonus->getSetting('promo_double', 0.5)) ?>" step="0.1" min="0" max="2.0">
                                                            <span class="input-group-text">+ bonus</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Tab 3: Time -->
                        <div class="tab-pane fade" id="time" role="tabpanel">
                            <form id="timeForm">
                                <div class="row">
                                    <!-- Intervals -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-info text-white">
                                                <i class="bi bi-clock-history me-2"></i>Intervals
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-4">
                                                    <label for="cronInterval" class="form-label">
                                                        Cron interval: <span class="slider-value" id="cronIntervalValue"><?= htmlspecialchars((string)$seedbonus->getSetting('cron_interval', 15)) ?></span> minutes
                                                    </label>
                                                    <input type="range" class="form-range" id="cronInterval" name="seedbonus_cron_interval"
                                                           min="5" max="60" step="5" value="<?= htmlspecialchars((string)$seedbonus->getSetting('cron_interval', 15)) ?>">
                                                    <div class="d-flex justify-content-between text-muted small">
                                                        <span>5 min</span>
                                                        <span>Frequent</span>
                                                        <span>30 min</span>
                                                        <span>Rare</span>
                                                        <span>60 min</span>
                                                    </div>
                                                </div>

                                                <div class="mb-4">
                                                    <label for="announceInterval" class="form-label">
                                                        Announce interval: <span class="slider-value" id="announceIntervalValue"><?= htmlspecialchars((string)$seedbonus->getSetting('announce_interval', 15)) ?></span> minutes
                                                    </label>
                                                    <input type="range" class="form-range" id="announceInterval" name="seedbonus_announce_interval"
                                                           min="5" max="60" step="5" value="<?= htmlspecialchars((string)$seedbonus->getSetting('announce_interval', 15)) ?>">
                                                    <div class="d-flex justify-content-between text-muted small">
                                                        <span>5 min</span>
                                                        <span>15 min</span>
                                                        <span>30 min</span>
                                                        <span>60 min</span>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="historyDays" class="form-label">
                                                        Activity history: <span class="slider-value" id="historyDaysValue"><?= htmlspecialchars((string)$seedbonus->getSetting('history_days', 1)) ?></span> days
                                                    </label>
                                                    <input type="range" class="form-range" id="historyDays" name="seedbonus_history_days"
                                                           min="1" max="30" step="1" value="<?= htmlspecialchars((string)$seedbonus->getSetting('history_days', 1)) ?>">
                                                    <div class="text-muted small">
                                                        How many days of user activity to consider
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Time Heuristic -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-warning text-white">
                                                <i class="bi bi-person-workspace me-2"></i>Seeding Time Heuristic
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted small mb-3">
                                                    Automatic estimation of seeding time based on torrent count.
                                                </p>
                                                
                                                <div class="mb-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="enableHeuristic" 
                                                               name="seedbonus_enable_heuristic" <?= $seedbonus->getSetting('enable_heuristic', true) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="enableHeuristic">
                                                            Use heuristic (recommended)
                                                        </label>
                                                    </div>
                                                </div>

                                                <div id="heuristicSettings">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Torrents</th>
                                                                <th>Assumed Time</th>
                                                                <th>Setting</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $heuristics = [
                                                                '50' => '≥ 50',
                                                                '40' => '≥ 40',
                                                                '30' => '≥ 30',
                                                                '20' => '≥ 20',
                                                                '10' => '≥ 10',
                                                                '5' => '≥ 5',
                                                                '1' => '1-4'
                                                            ];
                                                            ?>
                                                            
                                                            <?php foreach ($heuristics as $key => $label): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($label) ?></td>
                                                                <td><span id="heuristic<?= htmlspecialchars((string)$key) ?>"><?= htmlspecialchars((string)$seedbonus->getSetting("heuristic_$key", 24.0)) ?></span> h/day</td>
                                                                <td><input type="number" class="form-control form-control-sm" 
                                                                           name="seedbonus_heuristic_<?= htmlspecialchars((string)$key) ?>" 
                                                                           value="<?= htmlspecialchars((string)$seedbonus->getSetting("heuristic_$key", 24.0)) ?>" 
                                                                           step="1" min="1" max="24"></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>








                     <!-- Tab 4: Preview -->
<div class="tab-pane fade" id="preview" role="tabpanel">
    <div class="row">
        <!-- LEFT COLUMN: Test Calculation -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-lg h-100" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
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
                    <!-- Summary Stats -->
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="text-center p-3 rounded-3 bg-white shadow-sm">
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-hdd-stack me-1"></i> Torrents
                                </div>
                                <div class="display-6 fw-bold text-primary" id="previewTorrents">
                                    <?= htmlspecialchars((string)$preview['torrents']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 rounded-3 bg-white shadow-sm">
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-gem me-1"></i> Raw Bonus
                                </div>
                                <div class="display-6 fw-bold text-success" id="previewRawBonus">
                                    <?= htmlspecialchars(number_format($preview['raw_bonus'], 1)) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed Calculation -->
                    <div class="card border-0 bg-white shadow-sm">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3 text-dark">
                                <i class="bi bi-calculator me-2"></i>Calculation Breakdown
                            </h6>
                            
                            <div class="row g-3">
                                <!-- Left Column -->
                                <div class="col-6">
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div class="text-muted small">Base Rate</div>
                                        <div class="fw-bold text-dark" id="previewBaseBonus">
                                            <?= htmlspecialchars(number_format($preview['base_bonus'], 1)) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div class="text-muted small">Torrent Multiplier</div>
                                        <div class="fw-bold text-warning" id="previewCapMul">
                                            ×<?= htmlspecialchars(number_format($preview['cap_mul'], 2)) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div class="text-muted small">Avg Seeding Time</div>
                                        <div class="fw-bold text-info" id="previewAvgHours">
                                            <?= htmlspecialchars(number_format($preview['avg_hours'] * 60, 0)) ?> min
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column -->
                                <div class="col-6">
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div class="text-muted small">Hour Cap</div>
                                        <div class="fw-bold text-danger" id="previewHourCap">
                                            <?= htmlspecialchars(number_format($preview['hour_cap'], 1)) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div class="text-muted small">Theoretical Hourly</div>
                                        <div class="fw-bold text-primary" id="previewHourlyTheoretical">
                                            <?= htmlspecialchars(number_format($preview['hourly_theoretical'], 1)) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div class="text-muted small">Final Hourly</div>
                                        <div class="fw-bold text-success" id="previewFinalHourly">
                                            <?= htmlspecialchars(number_format($preview['final_hourly'], 1)) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Results Highlight -->
                            <div class="mt-4">
                                <div class="row g-3">
                                    <div class="col-4">
                                        <div class="text-center p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25">
                                            <div class="text-muted small mb-1">Per 15min Run</div>
                                            <div class="h4 fw-bold text-primary mb-0" id="previewPerRun">
                                                <?= htmlspecialchars(number_format($preview['per_run'], 1)) ?>
                                            </div>
                                            <small class="text-muted">points</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-center p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                                            <div class="text-muted small mb-1">Per Hour</div>
                                            <div class="h4 fw-bold text-success mb-0">
                                                <?= htmlspecialchars(number_format($preview['per_run'] * 4, 1)) ?>
                                            </div>
                                            <small class="text-muted">points</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-center p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                            <div class="text-muted small mb-1">Daily Total</div>
                                            <div class="h4 fw-bold text-warning mb-0" id="previewDaily">
                                                <?= htmlspecialchars(number_format($preview['daily'])) ?>
                                            </div>
                                            <small class="text-muted">points</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Info Note -->
                            <div class="alert alert-info border-0 mt-4 small">
                                <div class="d-flex">
                                    <i class="bi bi-info-circle-fill text-info me-2 mt-1"></i>
                                    <div>
                                        <strong>Note:</strong> This is a <strong>simulated calculation</strong> using optimized torrent mix 
                                        (Freeleech, 3+ leechers, large files). Real user values may vary.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Inflation Forecast -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-lg h-100">
                <div class="card-header bg-transparent border-0 py-3 text-dark">
                    <div class="d-flex align-items-center">
                        <div class="bg-dark bg-opacity-25 p-2 rounded-circle me-3">
                            <i class="bi bi-graph-up-arrow text-dark"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Inflation Forecast</h5>
                            <small class="text-dark text-opacity-75">System-wide points emission</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- User Input -->
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-dark bg-opacity-10">
                                <label class="form-label text-dark text-opacity-90 small mb-1">
                                    <i class="bi bi-people me-1"></i> Active Users
                                </label>
                                <input type="number" 
                                       class="form-control form-control-lg bg-dark bg-opacity-10 border-dark border-opacity-25 text-dark" 
                                       id="inflationUsers" 
                                       value="100" 
                                       min="1"
                                       style="font-weight: 500;">
                                <div class="text-dark text-opacity-75 small mt-1">Adjust for simulation</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-dark bg-opacity-10">
                                <div class="text-dark text-opacity-90 small mb-1">
                                    <i class="bi bi-cash-coin me-1"></i> Avg User Bonus
                                </div>
                                <div class="display-6 fw-bold text-dark" id="inflationAvgBonus">
                                    <?= htmlspecialchars(number_format($preview['daily'])) ?>
                                </div>
                                <div class="text-dark text-opacity-75 small">points/day</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Forecast Cards -->
                    <div class="row g-3">
                        <!-- Daily Release -->
                        <div class="col-6">
                            <div class="p-4 rounded-3 bg-dark bg-opacity-10 border border-dark border-opacity-25 h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success bg-opacity-25 p-2 rounded-circle me-3">
                                        <i class="bi bi-sun text-dark fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-dark text-opacity-75 small">Daily Release</div>
                                        <div class="h3 fw-bold text-dark mb-0" id="inflationDaily">
                                            <?= htmlspecialchars(number_format($preview['daily'] * 100)) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-dark text-opacity-75 small">
                                    Points entering system daily
                                </div>
                            </div>
                        </div>
                        
                        <!-- Monthly Release -->
                        <div class="col-6">
                            <div class="p-4 rounded-3 bg-dark bg-opacity-10 border border-dark border-opacity-25 h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-warning bg-opacity-25 p-2 rounded-circle me-3">
                                        <i class="bi bi-calendar-month text-dark fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-dark text-opacity-75 small">Monthly Release</div>
                                        <div class="h3 fw-bold text-dark mb-0" id="inflationMonthly">
                                            <?= htmlspecialchars(number_format($preview['daily'] * 100 * 30)) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-dark text-opacity-75 small">
                                    30-day projection
                                </div>
                            </div>
                        </div>
                        
                        <!-- Avg Balance -->
                        <div class="col-12">
                            <div class="p-4 rounded-3 bg-dark bg-opacity-10 border border-dark border-opacity-25 mt-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-info bg-opacity-25 p-2 rounded-circle me-3">
                                        <i class="bi bi-pie-chart text-dark fs-5"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="text-dark text-opacity-75 small">Average User Balance</div>
                                        <div class="h2 fw-bold text-dark mb-0" id="inflationAvgBalance">
                                            <?= htmlspecialchars(number_format(($preview['daily'] * 100 * 30) / 500)) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-dark text-opacity-75 small">Based on</div>
                                        <div class="h5 fw-bold text-dark">500 users</div>
                                    </div>
                                </div>
                                <div class="progress bg-dark bg-opacity-25" style="height: 8px;">
                                    <div class="progress-bar bg-info" 
                                         role="progressbar" 
                                         style="width: <?= min(100, (($preview['daily'] * 100 * 30) / 500) / 1000 * 100) ?>%"
                                         aria-valuenow="<?= ($preview['daily'] * 100 * 30) / 500 ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="1000">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-dark text-opacity-75">Low</small>
                                    <small class="text-dark text-opacity-75">Average Balance</small>
                                    <small class="text-dark text-opacity-75">High</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Warning Note -->
                    <div class="alert alert-warning border-0 mt-4 bg-dark bg-opacity-10 border-warning border-opacity-25">
                        <div class="d-flex">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2 mt-1"></i>
                            <div class="text-dark">
                                <strong>Monitor closely:</strong> High daily release may cause inflation. 
                                Consider adjusting base bonus or hour cap if emission is too high.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
/* Custom styles for better appearance */
.card {
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15) !important;
}

.stat-card {
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
}

.bg-opacity-10 {
    background-opacity: 0.1;
}

.border-opacity-25 {
    border-color-opacity: 0.25;
}

.form-control:focus {
    box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
}

/* Custom scrollbar for cards */
.card-body {
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.1) transparent;
}

.card-body::-webkit-scrollbar {
    width: 6px;
}

.card-body::-webkit-scrollbar-track {
    background: transparent;
}

.card-body::-webkit-scrollbar-thumb {
    background-color: rgba(0,0,0,0.1);
    border-radius: 3px;
}
</style>






						
						
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    // Preset configurations from PHP
    const presets = <?= json_encode($seedbonus->getPresets()) ?>;
    
    // Debug: check if presets are loaded
    console.log('Presets loaded:', presets);
    console.log('Conservative preset:', presets.conservative);

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing...');
        initSliders();
        initEventListeners();
        updateSliderValues();
    });

    // Initialize sliders
    function initSliders() {
        const sliders = document.querySelectorAll('input[type="range"]');
        sliders.forEach(slider => {
            const valueSpan = document.getElementById(slider.id + 'Value');
            if (valueSpan) {
                slider.addEventListener('input', function() {
                    valueSpan.textContent = this.value;
                });
            }
        });
    }

    // Initialize event listeners
    function initEventListeners() {
        console.log('Initializing event listeners...');
        
        // Presets - добавляем обработчик клика
        document.querySelectorAll('.config-badge').forEach(badge => {
            console.log('Adding listener to badge:', badge.dataset.preset);
            badge.addEventListener('click', function(e) {
                e.preventDefault();
                const preset = this.dataset.preset;
                console.log('Preset clicked:', preset);
                loadPreset(preset);
            });
            
            // Также добавляем стиль курсора при наведении
            badge.style.cursor = 'pointer';
        });

        // Save button
        document.getElementById('saveBtn').addEventListener('click', function(e) {
            e.preventDefault();
            saveSettings2();
        });
        
        // Reset button
        document.getElementById('resetBtn').addEventListener('click', function(e) {
            e.preventDefault();
            resetSettings();
        });
        
        // Copy code button
        document.getElementById('copyCodeBtn').addEventListener('click', copyCode);
        
        // Download code button
        document.getElementById('downloadCodeBtn').addEventListener('click', downloadCode);
        
        // Inflation users input
        document.getElementById('inflationUsers').addEventListener('input', updateInflation);
    }

    // Update slider values on page load
    function updateSliderValues() {
        document.querySelectorAll('input[type="range"]').forEach(slider => {
            const valueSpan = document.getElementById(slider.id + 'Value');
            if (valueSpan) {
                valueSpan.textContent = slider.value;
            }
        });
    }

    // Load preset - ФИКСИРОВАННАЯ ВЕРСИЯ
    function loadPreset(presetName) {
        console.log('Loading preset:', presetName);
        console.log('Available presets:', Object.keys(presets));
        
        if (!presets[presetName]) {
            console.error('Preset not found:', presetName);
            showNotification(`Preset "${presetName}" not found`, 'danger');
            return;
        }
        
        if (!confirm(`Load "${presetName}" preset? Current settings will be overwritten.`)) {
            return;
        }

        // Используем AJAX для сохранения пресета в БД
        const formData = new FormData();
        formData.append('action', 'load_preset');
        formData.append('preset', presetName);
        
        console.log('Sending AJAX request...');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response received:', response);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                showNotification(data.message, 'success');
                // Перезагружаем страницу через 1 секунду для обновления настроек
                setTimeout(() => {
                    console.log('Reloading page...');
                    window.location.reload();
                }, 1000);
            } else {
                showNotification(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading preset: ' + error.message, 'danger');
        });
    }

    // Save settings
    function saveSettings2() {
        console.log('Saving settings...');
        const forms = ['basicForm', 'multipliersForm', 'timeForm'];
        const formData = new FormData();
        
        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) {
                const formDataTemp = new FormData(form);
                for (const [key, value] of formDataTemp.entries()) {
                    console.log('Form field:', key, '=', value);
                    formData.append(key, value);
                }
            }
        });
        
        formData.append('action', 'save');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
            } else {
                showNotification(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving settings: ' + error.message, 'danger');
        });
    }

    // Reset settings
    function resetSettings() {
        if (confirm('Reset all settings to default values?')) {
            loadPreset('balanced');
        }
    }

    // Copy code to clipboard
    function copyCode() {
        const code = document.getElementById('generatedCode').textContent;
        navigator.clipboard.writeText(code).then(() => {
            showNotification('Code copied to clipboard', 'success');
        }).catch(err => {
            console.error('Failed to copy: ', err);
            showNotification('Failed to copy code', 'danger');
        });
    }

    // Download code file
    function downloadCode() {
        const code = document.getElementById('generatedCode').textContent;
        const blob = new Blob([code], { type: 'text/php' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'seedbonus_config.php';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showNotification('File downloaded', 'success');
    }

    // Update inflation calculation
    function updateInflation() {
        const users = parseInt(document.getElementById('inflationUsers').value) || 100;
        const avgBonus = parseFloat(document.getElementById('previewDaily').textContent.replace(/,/g, '')) || 3000;
        
        const daily = users * avgBonus;
        const monthly = daily * 30;
        const avgBalance = monthly / 500;
        
        document.getElementById('inflationDaily').textContent = Math.round(daily).toLocaleString();
        document.getElementById('inflationMonthly').textContent = Math.round(monthly).toLocaleString();
        document.getElementById('inflationAvgBalance').textContent = Math.round(avgBalance).toLocaleString();
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const notification = document.getElementById('notification');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        notification.innerHTML = '';
        notification.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode === notification) {
                alert.remove();
            }
        }, 3000);
    }
</script>


<?php
stdfoot();
?>