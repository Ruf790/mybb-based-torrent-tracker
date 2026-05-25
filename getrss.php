<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

declare(strict_types=1);

define('R_VERSION', 'v1.7');
require_once 'global.php';

if (!isset($CURUSER) || ($CURUSER["id"] ?? 0) === 0) {
    print_no_permission();
}

gzip();

$lang->load('getrss');

$allowed_timezones = [
    '-12', '-11', '-10', '-9', '-8', '-7', '-6', '-5', '-4', '-3.5', '-3', 
    '-2', '-1', '0', '1', '2', '3', '3.5', '4', '4.5', '5', '5.5', '6', 
    '7', '8', '9', '9.5', '10', '11', '12'
];

$allowed_showrows = ['5', '10', '20', '30', '40', '50'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queries = [];
    $link = $BASEURL . '/rss.php?secret_key=' . ($CURUSER['passkey'] ?? '') . '&';
    
    // Feed type handling
    $feedtype = in_array($_POST['feedtype'] ?? '', ['download', 'details'], true) 
        ? $_POST['feedtype'] 
        : 'details';
    $queries[] = 'feedtype=' . $feedtype;

    // Timezone handling
    $timezone = in_array($_POST['timezone'] ?? '', $allowed_timezones, true)
        ? $_POST['timezone']
        : '1';
    $queries[] = 'timezone=' . $timezone;

    // Show rows handling
    $showrows = in_array($_POST['showrows'] ?? '', $allowed_showrows, true)
        ? $_POST['showrows']
        : '20';
    $queries[] = 'showrows=' . $showrows;

    // Categories handling
    if (isset($_POST['showall'])) {
        $queries[] = 'categories=all';
    } else {
		
		$cats_raw = trim($_POST['categories_selected'] ?? '');
    if (empty($cats_raw)) {
        $queries[] = 'categories=all';
    } else {
        $ids = array_filter(array_map('intval', explode(',', $cats_raw)));
        $queries[] = !empty($ids) ? 'categories=' . implode(',', $ids) : 'categories=all';
    }
        
    }

    // Build final URL
    $final_queries = implode('&', $queries);
    if ($final_queries) {
        $link .= $final_queries;
    }

    // Output results - ПЕРЕМЕЩАЕМ JavaScript В НАЧАЛО ВЫВОДА
    stdhead($lang->getrss['title'] ?? 'RSS Feed');
    
    echo '
    <script>
    // Функция копирования RSS ссылки - ОПРЕДЕЛЯЕМ ПЕРВОЙ
    function copyRssLink() {
        const copyText = document.getElementById("rssLink");
        if (!copyText) return;
        
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        
        try {
            const successful = document.execCommand("copy");
            const btn = event.target.closest("button");
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = \'<i class="fas fa-check me-1"></i>Copied!\';
                btn.classList.remove("btn-outline-secondary");
                btn.classList.add("btn-success");
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove("btn-success");
                    btn.classList.add("btn-outline-secondary");
                }, 2000);
            }
        } catch (err) {
            console.error("Copy failed:", err);
            alert("Failed to copy link. Please copy it manually.");
        }
    }
    </script>

    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-rss me-2"></i>' . ($lang->getrss['done2'] ?? 'RSS Feed Generated') . '
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    ' . ($lang->getrss['copy_link'] ?? 'Copy the link below to your RSS reader') . '
                </div>
                <div class="input-group">
                    <input type="text" class="form-control" value="' . htmlspecialchars($link) . '" id="rssLink" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyRssLink()">
                        <i class="fas fa-copy me-1"></i>Copy
                    </button>
                </div>
                <div class="mt-3">
                    <a href="' . htmlspecialchars($link) . '" class="btn btn-success me-2" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i>Test RSS Feed
                    </a>
                    <a href="/getrss.php" class="btn btn-outline-primary">
                        <i class="fas fa-undo me-1"></i>Generate Another
                    </a>
                </div>
            </div>
        </div>
    </div>';

    stdfoot();
    exit;
}

// Display the form
stdhead($lang->getrss['title'] ?? 'Get RSS Feed');




?>
<script>
// Мульти-выбор категорий
const selectedCats = new Set();

function toggleCategory(btn) {
    const id = btn.dataset.id;
    if (selectedCats.has(id)) {
        selectedCats.delete(id);
        btn.classList.remove('active');
    } else {
        selectedCats.add(id);
        btn.classList.add('active');
    }
    document.getElementById('categoriesSelected').value = [...selectedCats].join(',');
    const count = selectedCats.size;
    document.getElementById('catLabel').innerHTML = count > 0
        ? '<i class="fas fa-check-circle text-success me-1"></i><strong>' + count + ' categor' + (count === 1 ? 'y' : 'ies') + ' selected</strong>'
        : '<i class="fas fa-hand-pointer me-1"></i>Click to select / deselect categories';
}

function selectAllCategories() {
    document.querySelectorAll('.cat-pick-btn').forEach(btn => {
        selectedCats.add(btn.dataset.id);
        btn.classList.add('active');
    });
    document.getElementById('categoriesSelected').value = [...selectedCats].join(',');
    document.getElementById('catLabel').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i><strong>All categories selected</strong>';
}

function deselectAllCategories() {
    selectedCats.clear();
    document.querySelectorAll('.cat-pick-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('categoriesSelected').value = '';
    document.getElementById('catLabel').innerHTML = '<i class="fas fa-hand-pointer me-1"></i>Click to select / deselect categories';
}
</script>

<?



// Добавляем ВСЕ JavaScript функции в начале вывода
echo '



<style>
.category-icon-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.cat-pick-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 18px;
    min-width: 90px;
    border: 1.5px solid #dee2e6;
    border-radius: 14px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #6c757d;
    font-size: 0.78rem;
    font-weight: 500;
    line-height: 1.2;
    text-align: center;
}

.cat-pick-btn i {
    font-size: 1.8rem;
    transition: transform 0.2s ease;
}

.cat-pick-btn:hover {
    border-color: #0d6efd;
    color: #0d6efd;
    background: #f0f5ff;
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(13,110,253,0.15);
}

.cat-pick-btn:hover i {
    transform: scale(1.2);
}

.cat-pick-btn.active {
    border-color: #0d6efd;
    border-width: 2px;
    background: #e7f1ff;
    color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13,110,253,0.12);
    transform: translateY(-2px);
}

.cat-pick-btn.active span {
    font-weight: 700;
}


</style>




<script>
// Функция копирования RSS ссылки
function copyRssLink() {
    const copyText = document.getElementById("rssLink");
    if (!copyText) return;
    
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    
    try {
        const successful = document.execCommand("copy");
        const btn = event.target.closest("button");
        if (btn) {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = \'<i class="fas fa-check me-1"></i>Copied!\';
            btn.classList.remove("btn-outline-secondary");
            btn.classList.add("btn-success");
            
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove("btn-success");
                btn.classList.add("btn-outline-secondary");
            }, 2000);
        }
    } catch (err) {
        console.error("Copy failed:", err);
        alert("Failed to copy link. Please copy it manually.");
    }
}

// Form validation
document.addEventListener("DOMContentLoaded", function() {
    const forms = document.querySelectorAll(".needs-validation");
    
    Array.from(forms).forEach(function(form) {
        form.addEventListener("submit", function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        }, false);
    });
});
</script>';

if (!isset($_categoriesC) || !is_array($_categoriesC)) {
    require_once TSDIR . '/cache/categories.php';
}

// Timezone options array for cleaner code
$timezone_options = [
    '-12' => '(GMT -12:00) Eniwetok, Kwajalein',
    '-11' => '(GMT -11:00) Midway Island, Samoa',
    '-10' => '(GMT -10:00) Hawaii',
    '-9' => '(GMT -9:00) Alaska',
    '-8' => '(GMT -8:00) Pacific Time (US & Canada)',
    '-7' => '(GMT -7:00) Mountain Time (US & Canada)',
    '-6' => '(GMT -6:00) Central Time (US & Canada), Mexico City',
    '-5' => '(GMT -5:00) Eastern Time (US & Canada), Bogota, Lima',
    '-4' => '(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz',
    '-3.5' => '(GMT -3:30) Newfoundland',
    '-3' => '(GMT -3:00) Brazil, Buenos Aires, Georgetown',
    '-2' => '(GMT -2:00) Mid-Atlantic',
    '-1' => '(GMT -1:00 hour) Azores, Cape Verde Islands',
    '0' => '(GMT) Western Europe Time, London, Lisbon, Casablanca',
    '1' => '(GMT +1:00 hour) Brussels, Copenhagen, Madrid, Paris',
    '2' => '(GMT +2:00) Kaliningrad, South Africa',
    '3' => '(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg',
    '3.5' => '(GMT +3:30) Tehran',
    '4' => '(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi',
    '4.5' => '(GMT +4:30) Kabul',
    '5' => '(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent',
    '5.5' => '(GMT +5:30) Bombay, Calcutta, Madras, New Delhi',
    '6' => '(GMT +6:00) Almaty, Dhaka, Colombo',
    '7' => '(GMT +7:00) Bangkok, Hanoi, Jakarta',
    '8' => '(GMT +8:00) Beijing, Perth, Singapore, Hong Kong',
    '9' => '(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk',
    '9.5' => '(GMT +9:30) Adelaide, Darwin',
    '10' => '(GMT +10:00) Eastern Australia, Guam, Vladivostok',
    '11' => '(GMT +11:00) Magadan, Solomon Islands, New Caledonia',
    '12' => '(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka'
];

echo '
<form method="post" action="/getrss.php" name="rss" class="needs-validation" novalidate>
    
	
<div class="container mt-4 mb-0">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">
            <i class="fas fa-th-large me-2"></i>Categories
        </div>
        <div class="card-body">
            <input type="hidden" name="categories_selected" id="categoriesSelected" value="">
            <div class="category-icon-picker" id="catPicker">';

foreach ($_categoriesC as $cat) {
    echo '<button type="button"
            class="cat-pick-btn"
            data-id="' . (int)$cat['id'] . '"
            title="' . htmlspecialchars($cat['name']) . '"
            onclick="toggleCategory(this)">
        <i class="' . htmlspecialchars($cat['icon'] ?? 'fas fa-folder') . '"></i>
        <span>' . htmlspecialchars($cat['name']) . '</span>
    </button>';
}

echo '</div>
            <div class="mt-2 small text-muted" id="catLabel">
                <i class="fas fa-hand-pointer me-1"></i>Click to select / deselect categories
            </div>
        </div>
    </div>
</div>

    <div class="container my-4">
        <!-- Quick Selection Buttons -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-mouse-pointer me-2"></i>Quick Selection
            </div>
            <div class="card-body">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-success" onclick="selectAllCategories()">
                        <i class="fas fa-check-square me-1"></i>Select All Categories
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deselectAllCategories()">
                        <i class="fas fa-times-circle me-1"></i>Deselect All Categories
                    </button>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="showall" id="showall" value="1" onclick="handleSelectAll(this)">
                    <label class="form-check-label fw-semibold" for="showall">
                        <i class="fas fa-asterisk me-1"></i>Select All Categories (Include All Future Categories)
                    </label>
                </div>
            </div>
        </div>

        <!-- Feed Type -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-link me-2"></i>Feed Type
            </div>
            <div class="card-body">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="feedtype" id="feedtype_details" value="details" checked>
                    <label class="form-check-label" for="feedtype_details">
                        <i class="fas fa-globe me-1"></i>Web Link
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="feedtype" id="feedtype_download" value="download">
                    <label class="form-check-label" for="feedtype_download">
                        <i class="fas fa-download me-1"></i>Download Link
                    </label>
                </div>
            </div>
        </div>

        <!-- Timezone & Rows Per Page -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-cog me-2"></i>Settings
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="timezone" class="form-label fw-semibold">
                            <i class="fas fa-clock me-1"></i>Select Your Timezone:
                        </label>
                        <select class="form-select" name="timezone" id="timezone" required>';
                        
foreach ($timezone_options as $value => $label) {
    $selected = $value === '1' ? ' selected' : '';
    echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
}

echo '
                        </select>
                        <div class="invalid-feedback">Please select a valid timezone.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="showrows" class="form-label fw-semibold">
                            <i class="fas fa-list me-1"></i>Rows Per Page:
                        </label>
                        <select class="form-select" name="showrows" id="showrows" required>';
                        
foreach ($allowed_showrows as $rows) {
    $selected = $rows === '20' ? ' selected' : '';
    echo '<option value="' . $rows . '"' . $selected . '>' . $rows . '</option>';
}

echo '
                        </select>
                        <div class="invalid-feedback">Please select number of rows to show.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="row">
            <div class="col-12">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-magic me-2"></i>Generate RSS Link
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>';

stdfoot();
?>