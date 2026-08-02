<?php
declare(strict_types=1);



if (!defined ('STAFF_PANEL')) 
{
    exit ('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}




// Проверяем отправку формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_torrent_settings') {
    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        http_response_code(403);
        echo 'Invalid security token';
        exit;
    }
    save_torrent_settings();
}


$promo_settings = [];
$q = $db->sql_query_prepared("SELECT name, value FROM settings WHERE name IN (
    'prorules','randomhalfleech','randomfree','randomtwoup','randomtwoupfree',
    'randomtwouphalfdown','randomthirtypercentdown','largesize','largepro',
    'expirehalfleech','expirefree','expiretwoup','expiretwoupfree',
    'expiretwouphalfleech','expirethirtypercentleech','expirenormal',
    'halfleechbecome','freebecome','twoupbecome','twoupfreebecome',
    'twouphalfleechbecome','thirtypercentleechbecome','normalbecome',
    'hotdays','hotseeder','uploaderdouble','deldeadtorrent'
)");
while ($r = $db->fetch_array($q)) $promo_settings[$r['name']] = $r['value'];

$prorules_torrent              = $promo_settings['prorules']                    ?? 'yes';
$randomhalfleech_torrent       = $promo_settings['randomhalfleech']             ?? 5;
$randomfree_torrent            = $promo_settings['randomfree']                  ?? 2;
$randomtwoup_torrent           = $promo_settings['randomtwoup']                 ?? 2;
$randomtwoupfree_torrent       = $promo_settings['randomtwoupfree']             ?? 1;
$randomtwouphalfdown_torrent   = $promo_settings['randomtwouphalfdown']         ?? 0;
$randomthirtypercentdown_torrent = $promo_settings['randomthirtypercentdown']   ?? 0;
$largesize_torrent             = $promo_settings['largesize']                   ?? 12;
$largepro_torrent              = (int)($promo_settings['largepro']              ?? 5);
$expirehalfleech_torrent       = $promo_settings['expirehalfleech']             ?? 70;
$expirefree_torrent            = $promo_settings['expirefree']                  ?? 60;
$expiretwoup_torrent           = $promo_settings['expiretwoup']                 ?? 60;
$expiretwoupfree_torrent       = $promo_settings['expiretwoupfree']             ?? 30;
$expiretwouphalfleech_torrent  = $promo_settings['expiretwouphalfleech']        ?? 30;
$expirethirtypercentleech_torrent = $promo_settings['expirethirtypercentleech'] ?? 30;
$expirenormal_torrent          = $promo_settings['expirenormal']                ?? 0;
$halfleechbecome_torrent       = (int)($promo_settings['halfleechbecome']       ?? 1);
$freebecome_torrent            = (int)($promo_settings['freebecome']            ?? 1);
$twoupbecome_torrent           = (int)($promo_settings['twoupbecome']           ?? 1);
$twoupfreebecome_torrent       = (int)($promo_settings['twoupfreebecome']       ?? 1);
$twouphalfleechbecome_torrent  = (int)($promo_settings['twouphalfleechbecome']  ?? 1);
$thirtypercentleechbecome_torrent = (int)($promo_settings['thirtypercentleechbecome'] ?? 1);
$normalbecome_torrent          = (int)($promo_settings['normalbecome']          ?? 1);
$hotdays_torrent               = $promo_settings['hotdays']                     ?? 7;
$hotseeder_torrent             = $promo_settings['hotseeder']                   ?? 5;
$uploaderdouble_torrent        = $promo_settings['uploaderdouble']              ?? 'no';
$deldeadtorrent_torrent        = $promo_settings['deldeadtorrent']              ?? 'no';





function save_torrent_settings(): void 
{
    global $db, $lang, $_this_script_;
    
    $data = [
        'prorules'                 => $_POST['prorules'] ?? 'no',
        'randomhalfleech'          => (int)($_POST['randomhalfleech'] ?? 5),
        'randomfree'               => (int)($_POST['randomfree'] ?? 2),
        'randomtwoup'              => (int)($_POST['randomtwoup'] ?? 2),
        'randomtwoupfree'          => (int)($_POST['randomtwoupfree'] ?? 1),
        'randomtwouphalfdown'      => (int)($_POST['randomtwouphalfdown'] ?? 0),
        'randomthirtypercentdown'  => (int)($_POST['randomthirtypercentdown'] ?? 0),
        'largesize'                => (string)(float)($_POST['largesize'] ?? 20.0),
        'largepro'                 => (int)($_POST['largepro'] ?? 2),
        'expirehalfleech'  => (int)($_POST['expirehalfleech'] ?? 150),
        'expirefree'       => (int)($_POST['expirefree'] ?? 60),
        'expiretwoup'      => (int)($_POST['expiretwoup'] ?? 60),
        'expiretwoupfree'  => (int)($_POST['expiretwoupfree'] ?? 30),
        'expiretwouphalfleech'     => (int)($_POST['expiretwouphalfleech'] ?? 30),
        'expirethirtypercentleech' => (int)($_POST['expirethirtypercentleech'] ?? 30),
        'expirenormal'     => (int)($_POST['expirenormal'] ?? 0),
        'halfleechbecome'          => (int)($_POST['halfleechbecome'] ?? 1),
        'freebecome'               => (int)($_POST['freebecome'] ?? 1),
        'twoupbecome'              => (int)($_POST['twoupbecome'] ?? 1),
        'twoupfreebecome'          => (int)($_POST['twoupfreebecome'] ?? 1),
        'twouphalfleechbecome'     => (int)($_POST['twouphalfleechbecome'] ?? 1),
        'thirtypercentleechbecome' => (int)($_POST['thirtypercentleechbecome'] ?? 1),
        'normalbecome'             => (int)($_POST['normalbecome'] ?? 1),
        'hotdays'          => (int)($_POST['hotdays'] ?? 7),
        'hotseeder'        => (int)($_POST['hotseeder'] ?? 5),
        'uploaderdouble'   => $_POST['uploaderdouble'] ?? 'no',
        'deldeadtorrent'   => $_POST['deldeadtorrent'] ?? 'no',
    ];

    foreach ($data as $name => $value) {
        $db->sql_query_prepared('UPDATE settings SET value = ? WHERE name = ?', [(string)$value, $name]);
    }
    
    rebuild_settings();
    
    flash_message($lang->settings['settings_saved'] ?? 'Settings saved successfully!', 'success');
    header("Location: " . $_this_script_);
    exit;
}




stdhead('Torrent Promotion Settings');
$lang->load('settings');

// Показываем сообщение об успешном сохранении
if (isset($_GET['saved'])) {
    echo '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>' . ($lang->settings['settings_saved'] ?? 'Settings saved successfully!') . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

// Функция для создания селекта с Bootstrap классами
function promotion_selection_bootstrap(int $selected = 0, string $name = '', int $hide = 0): string 
{
    global $lang;
    
    $options = [
        1 => ['value' => 1, 'text' => $lang->settings['text_normal'] ?? 'Normal', 'badge' => 'secondary'],
        2 => ['value' => 2, 'text' => $lang->settings['text_free'] ?? 'Free', 'badge' => 'success'],
        3 => ['value' => 3, 'text' => $lang->settings['text_two_times_up'] ?? '2X Upload', 'badge' => 'info'],
        4 => ['value' => 4, 'text' => $lang->settings['text_free_two_times_up'] ?? 'Free + 2X', 'badge' => 'warning'],
        5 => ['value' => 5, 'text' => $lang->settings['text_half_down'] ?? '50% Leech', 'badge' => 'primary'],
        6 => ['value' => 6, 'text' => $lang->settings['text_half_down_two_up'] ?? '50% + 2X', 'badge' => 'danger'],
        7 => ['value' => 7, 'text' => $lang->settings['text_thirty_percent_down'] ?? '30% Leech', 'badge' => 'dark']
    ];
    
    $html = '<select class="form-select" name="' . htmlspecialchars($name) . '">';
    
    foreach ($options as $key => $option) {
        if ($hide === $key) {
            continue;
        }
        
        $isSelected = $selected === $option['value'] ? ' selected' : '';
        
        $html .= sprintf(
            '<option value="%d"%s>%s</option>',
            $option['value'],
            $isSelected,
            htmlspecialchars($option['text'])
        );
    }
    
    $html .= '</select>';
    return $html;
}

// Функция для безопасного вывода
function safe_echo($value): string 
{
    return htmlspecialchars((string)$value);
}

// Функция для получения значения из массива $promo
function get_torrent_setting(string $key, $default = '') 
{
    global $promo;
    return $promo[$key] ?? $default;
}
?>

<div class="container mt-3">
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-gift me-2"></i><?= safe_echo($lang->settings['head_torrent_settings'] ?? 'Torrent Settings') ?></h4>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="save_torrent_settings">
                        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                        
                        <!-- Правила промо-акций -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                   
								   <input class="form-check-input" type="checkbox" role="switch" id="prorules" name="prorules" 
                                      value="yes" <?= (($prorules_torrent ?? 'no') === 'yes') ? 'checked' : '' ?>>
								   
                                    <label class="form-check-label fw-bold" for="prorules">
                                        <?= safe_echo($lang->settings['row_promotion_rules'] ?? 'Promotion Rules') ?>
                                    </label>
                                    <div class="form-text text-muted">
                                        <?= safe_echo($lang->settings['text_promotion_rules_note'] ?? 'Enable some automatic promotion rules.') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Случайные промо-акции -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-random me-2"></i><?= safe_echo($lang->settings['row_random_promotion'] ?? 'Random Promotion') ?></h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?= safe_echo($lang->settings['text_random_promotion_note_one'] ?? 'Torrents promoted randomly by system upon uploading.') ?></p>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <tbody>
                                            <!-- 50% Leech -->
                                            <tr>
                                                <td width="40%">
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="randomhalfleech" 
                                                               value="<?= $randomhalfleech_torrent ?>" min="0" max="100" step="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?= safe_echo($lang->settings['text_half_down'] ?? '50% Leech') ?></span>
                                                    <?= safe_echo($lang->settings['text_halfleech_chance_becoming'] ?? '% chance becoming') ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- Free Leech -->
                                            <tr>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="randomfree" 
                                                               value="<?= $randomfree_torrent ?>" min="0" max="100" step="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?= safe_echo($lang->settings['text_free'] ?? 'Free Leech') ?></span>
                                                    <?= safe_echo($lang->settings['text_free_chance_becoming'] ?? '% chance becoming') ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- 2X Upload -->
                                            <tr>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="randomtwoup" 
                                                               value="<?= $randomtwoup_torrent ?>" min="0" max="100" step="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= safe_echo($lang->settings['text_two_times_up'] ?? '2X Upload') ?></span>
                                                    <?= safe_echo($lang->settings['text_twoup_chance_becoming'] ?? '% chance becoming') ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- Free + 2X -->
                                            <tr>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="randomtwoupfree" 
                                                               value="<?= $randomtwoupfree_torrent ?>" min="0" max="100" step="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning"><?= safe_echo($lang->settings['text_free_two_times_up'] ?? 'Free + 2X') ?></span>
                                                    <?= safe_echo($lang->settings['text_freetwoup_chance_becoming'] ?? '% chance becoming') ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- 50% + 2X -->
                                            <tr>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="randomtwouphalfdown" 
                                                               value="<?= $randomtwouphalfdown_torrent ?>" min="0" max="100" step="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?= safe_echo($lang->settings['text_half_down_two_up'] ?? '50% + 2X') ?></span>
                                                    <?= safe_echo($lang->settings['text_twouphalfleech_chance_becoming'] ?? '% chance becoming') ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- 30% Leech -->
                                            <tr>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="randomthirtypercentdown" 
                                                               value="<?= $randomthirtypercentdown_torrent ?>" min="0" max="100" step="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-dark"><?= safe_echo($lang->settings['text_thirty_percent_down'] ?? '30% Leech') ?></span>
                                                    <?= safe_echo($lang->settings['text_thirtypercentleech_chance_becoming'] ?? '% chance becoming') ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= safe_echo($lang->settings['text_random_promotion_note_two'] ?? "Set values to '0' to disable the rules.") ?>
                                </div>
                            </div>
                        </div>

                        <!-- Промо для больших торрентов -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-file-archive me-2"></i><?= safe_echo($lang->settings['row_large_torrent_promotion'] ?? 'Large Torrent Promotion') ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 align-items-center">
                                    <div class="col-auto">
                                        <label class="col-form-label"><?= safe_echo($lang->settings['text_torrent_larger_than'] ?? 'Torrents larger than') ?></label>
                                    </div>
                                    <div class="col-auto">
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="largesize" 
                                                   value="<?= $largesize_torrent ?>" min="0" step="0.1">
                                            <span class="input-group-text">GB</span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <label class="col-form-label"><?= safe_echo($lang->settings['text_gb_promoted_to'] ?? 'GB will be automatically promoted to') ?></label>
                                    </div>
                                    <div class="col-auto">
                                        <?= promotion_selection_bootstrap(
                                            $largepro_torrent,
                                            'largepro',
                                            1
                                        ) ?>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <?= safe_echo($lang->settings['text_by_system_upon_uploading'] ?? 'by system upon uploading.') ?>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <?= safe_echo($lang->settings['text_large_torrent_promotion_note'] ?? "Default '20', 'free'. Set torrent size to '0' to disable the rule.") ?>
                                </div>
                            </div>
                        </div>

                        <!-- Истечение промо-акций -->
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i><?= safe_echo($lang->settings['row_promotion_timeout'] ?? 'Promotion Timeout') ?></h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?= safe_echo($lang->settings['text_promotion_timeout_note_one'] ?? 'Promotion for torrents will expire after some time.') ?></p>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Promotion Type</th>
                                                <th>Will Become</th>
                                                <th>After (Days)</th>
                                                <th>Default</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- 50% Leech -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary"><?= safe_echo($lang->settings['text_half_down'] ?? '50% Leech') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $halfleechbecome_torrent,
                                                        'halfleechbecome',
                                                        5
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expirehalfleech" 
                                                           value="<?= $expirehalfleech_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 150</span>
                                                </td>
                                            </tr>
                                            
                                            <!-- Free Leech -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-success"><?= safe_echo($lang->settings['text_free'] ?? 'Free Leech') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $freebecome_torrent,
                                                        'freebecome',
                                                        2
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expirefree" 
                                                           value="<?= $expirefree_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 60</span>
                                                </td>
                                            </tr>
                                            
                                            <!-- 2X Upload -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-info"><?= safe_echo($lang->settings['text_two_times_up'] ?? '2X Upload') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $twoupbecome_torrent,
                                                        'twoupbecome',
                                                        3
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expiretwoup" 
                                                           value="<?= $expiretwoup_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 60</span>
                                                </td>
                                            </tr>
                                            
                                            <!-- Free + 2X -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-warning"><?= safe_echo($lang->settings['text_free_two_times_up'] ?? 'Free + 2X') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $twoupfreebecome_torrent,
                                                        'twoupfreebecome',
                                                        4
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expiretwoupfree" 
                                                           value="<?= $expiretwoupfree_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 30</span>
                                                </td>
                                            </tr>
                                            
                                            <!-- 50% + 2X -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-danger"><?= safe_echo($lang->settings['text_half_down_two_up'] ?? '50% + 2X') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $twouphalfleechbecome_torrent,
                                                        'twouphalfleechbecome',
                                                        6
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expiretwouphalfleech" 
                                                           value="<?= $expiretwouphalfleech_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 30</span>
                                                </td>
                                            </tr>
                                            
                                            <!-- 30% Leech -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-dark"><?= safe_echo($lang->settings['text_thirty_percent_down'] ?? '30% Leech') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $thirtypercentleechbecome_torrent,
                                                        'thirtypercentleechbecome',
                                                        7
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expirethirtypercentleech" 
                                                           value="<?= $expirethirtypercentleech_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 30</span>
                                                </td>
                                            </tr>
                                            
                                            <!-- Normal -->
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?></span>
                                                </td>
                                                <td>
                                                    <?= promotion_selection_bootstrap(
                                                        $normalbecome_torrent,
                                                        'normalbecome',
                                                        0
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="expirenormal" 
                                                           value="<?= $expirenormal_torrent ?>" min="0" step="1">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= safe_echo($lang->settings['text_normal'] ?? 'Normal') ?>, 0</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= safe_echo($lang->settings['text_promotion_timeout_note_two'] ?? 'Promotion for torrents will expire after some time.') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Дополнительные настройки -->
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Additional Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Hot Torrent Days -->
                                    <div class="col-md-6">
                                        <label for="hotdays" class="form-label">Hot Torrent Days</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="hotdays" name="hotdays" 
                                                   value="<?= $hotdays_torrent ?>" min="0" step="1">
                                            <span class="input-group-text">days</span>
                                        </div>
                                        <div class="form-text">Days to consider a torrent as "hot"</div>
                                    </div>
                                    
                                    <!-- Hot Seeder Threshold -->
                                    <div class="col-md-6">
                                        <label for="hotseeder" class="form-label">Hot Seeder Threshold</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="hotseeder" name="hotseeder" 
                                                   value="<?= $hotseeder_torrent ?>" min="0" step="1">
                                            <span class="input-group-text">seeders</span>
                                        </div>
                                        <div class="form-text">Minimum seeders for hot torrent</div>
                                    </div>
                                    
                                    <!-- Uploader Double Upload -->
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-3">
                                            
											<input class="form-check-input" type="checkbox" role="switch" id="uploaderdouble" name="uploaderdouble" 
       value="yes" <?= (($uploaderdouble_torrent ?? 'no') === 'yes') ? 'checked' : '' ?>>
<label class="form-check-label fw-bold" for="uploaderdouble">
  
											
											
                                                Uploader Double Upload
                                            </label>
                                            <div class="form-text">Enable double upload for torrent uploaders</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Dead Torrents -->
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-3">
                                            
											<input class="form-check-input" type="checkbox" role="switch" id="deldeadtorrent" name="deldeadtorrent" 
       value="yes" <?= ($deldeadtorrent_torrent === 'yes') ? 'checked' : '' ?>>
<label class="form-check-label fw-bold" for="deldeadtorrent">

											
											
											
                                                Delete Dead Torrents
                                            </label>
                                            <div class="form-text">Automatically delete torrents with no seeders</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Кнопки сохранения -->
                        <div class="row mt-4">
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i><?= safe_echo('Save Settings') ?>
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg ms-2">
                                    <i class="fas fa-undo me-2"></i><?= safe_echo('Reset') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
    .card {
        border-radius: 10px;
        border: none;
        margin-bottom: 1.5rem;
    }
    .card-header {
        border-radius: 10px 10px 0 0 !important;
        font-size: 1.1rem;
    }
    .form-control:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    .badge {
        font-size: 0.85em;
        padding: 0.4em 0.8em;
        margin-right: 0.5em;
    }
    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
        vertical-align: middle;
    }
    .table td {
        vertical-align: middle;
    }
    .input-group-text {
        background-color: #e9ecef;
        border-color: #ced4da;
        min-width: 60px;
        justify-content: center;
    }
    .form-text {
        font-size: 0.875em;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<?php
stdfoot();