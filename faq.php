<?php


require_once 'global.php';
gzip();

// Загружаем языковой файл
$lang->load('faq');

/**
 * Класс FAQ системы с использованием существующего $db класса
 */
class FAQSystem
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Экранирование строки для безопасности
     */
    private function escape($string): string
    {
        return $this->db->real_escape_string($string);
    }
    
    /**
     * Получить все категории FAQ
     */
    public function getCategories(): array
    {
        $result = $this->db->sql_query("
            SELECT id, name, description, icon_class 
            FROM faq 
            WHERE type = 'category' AND is_active = 1 
            ORDER BY disporder ASC
        ");
        
        $categories = [];
        while ($row = $this->db->fetch_array($result)) {
            $categories[] = $row;
        }
        $this->db->free_result($result);
        
        return $categories;
    }
    
    /**
     * Получить вопросы для категории
     */
    public function getCategoryItems(int $categoryId): array
    {
        $result = $this->db->sql_query("
            SELECT f.id, f.name, f.description, f.icon_class, f.views_count, 
                   c.name as category_name 
            FROM faq f 
            LEFT JOIN faq c ON (c.id = f.pid)
            WHERE f.type = 'item' AND f.pid = " . (int)$categoryId . " 
            AND f.is_active = 1 
            ORDER BY f.disporder ASC
        ");
        
        $items = [];
        while ($row = $this->db->fetch_array($result)) {
            $items[] = $row;
        }
        $this->db->free_result($result);
        
        return $items;
    }
    
    /**
     * Поиск в FAQ
     */
    public function searchFAQ(string $query, string $searchType = 'all'): array
    {
        $searchQuery = $this->escape($query);
        
        if ($searchType === 'titles') {
            $sql = "SELECT id, name, description 
                    FROM faq 
                    WHERE type = 'item' AND is_active = 1 
                    AND name LIKE '%" . $searchQuery . "%' 
                    ORDER BY disporder ASC";
        } else {
            $sql = "SELECT id, name, description 
                    FROM faq 
                    WHERE type = 'item' AND is_active = 1 
                    AND (name LIKE '%" . $searchQuery . "%' 
                    OR description LIKE '%" . $searchQuery . "%') 
                    ORDER BY disporder ASC";
        }
        
        $result = $this->db->sql_query($sql);
        $items = [];
        
        while ($row = $this->db->fetch_array($result)) {
            $items[] = $row;
        }
        $this->db->free_result($result);
        
        return $items;
    }
    
    /**
     * Получить популярные вопросы
     */
    public function getPopularFAQ(int $limit = 5): array
    {
        $result = $this->db->sql_query("
            SELECT id, name, views_count 
            FROM faq 
            WHERE type = 'item' AND is_active = 1 
            ORDER BY views_count DESC 
            LIMIT " . (int)$limit
        );
        
        $popular = [];
        while ($row = $this->db->fetch_array($result)) {
            $popular[] = $row;
        }
        $this->db->free_result($result);
        
        return $popular;
    }
    
    /**
     * Увеличить счетчик просмотров
     */
    public function incrementViewCount(int $faqId): void
    {
        $this->db->sql_query("
            UPDATE faq SET views_count = views_count + 1 
            WHERE id = " . (int)$faqId . " 
            AND type = 'item'
        ");
    }
    
    /**
     * Получить статистику FAQ
     */
    public function getStats(): array
    {
        $stats = [];
        
        // Всего вопросов
        $result = $this->db->sql_query("
            SELECT COUNT(*) as total 
            FROM faq 
            WHERE type = 'item' AND is_active = 1
        ");
        $row = $this->db->fetch_array($result);
        $stats['total_questions'] = $row['total'] ?? 0;
        $this->db->free_result($result);
        
        // Всего категорий
        $result = $this->db->sql_query("
            SELECT COUNT(*) as total 
            FROM faq 
            WHERE type = 'category' AND is_active = 1
        ");
        $row = $this->db->fetch_array($result);
        $stats['total_categories'] = $row['total'] ?? 0;
        $this->db->free_result($result);
        
        // Всего просмотров
        $result = $this->db->sql_query("
            SELECT SUM(views_count) as total 
            FROM faq 
            WHERE type = 'item'
        ");
        $row = $this->db->fetch_array($result);
        $stats['total_views'] = $row['total'] ?? 0;
        $this->db->free_result($result);
        
        return $stats;
    }
}

// Инициализация FAQ системы
$faqSystem = new FAQSystem($db);

// Получаем параметры
$do = isset($_GET['do']) ? $db->escape_string($_GET['do']) : '';
$view = isset($_GET['view']) ? $db->escape_string($_GET['view']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$words = isset($_GET['words']) ? $db->escape_string($_GET['words']) : '';
$searchtype = isset($_GET['searchtype']) ? $db->escape_string($_GET['searchtype']) : 'all';

// Начинаем вывод
stdhead($lang->faq['faqtitle']);

// Добавляем стили и скрипты
echo '
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">


<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
 
    
    .gradient-text {
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .category-card {
        transition: all 0.3s ease;
        border: 1px solid #e9ecef;
    }
    
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        border-color: rgba(102, 126, 234, 0.2);
    }
    
    .category-icon {
        background: var(--primary-gradient);
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }
    
    .accordion-button {
        border-radius: 8px !important;
        padding: 1rem 1.25rem;
        font-weight: 500;
    }
    
    .accordion-button:not(.collapsed) {
        background-color: rgba(102, 126, 234, 0.05);
        color: #667eea;
        box-shadow: none;
    }
    
    .faq-content {
        line-height: 1.8;
    }
    
    .faq-content ul, .faq-content ol {
        padding-left: 1.5rem;
    }
    
    .faq-content li {
        margin-bottom: 0.5rem;
    }
    
    .search-box input {
        border-radius: 50px;
        padding: 0.75rem 1.5rem;
        border: 2px solid #e9ecef;
    }
    
    .search-box input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
    }
    
    .search-box button {
        border-radius: 50px;
        padding: 0.75rem 2rem;
    }
    
    .highlight {
        background-color: #fff3cd;
        padding: 0.1rem 0.3rem;
        border-radius: 3px;
        font-weight: 600;
    }
    
    .stat-badge {
        background: var(--primary-gradient);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .category-card {
            margin-bottom: 1rem;
        }
        
        .search-box input, .search-box button {
            padding: 0.5rem 1rem;
        }
    }
</style>


';

// Навигация
echo '
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold gradient-text" href="' . $_SERVER['SCRIPT_NAME'] . '">
            <i class="bi bi-question-circle me-2"></i>' . $lang->faq['faqtitle'] . '
        </a>
        <div class="navbar-nav">
            <a class="nav-link" href="/">
                <i class="bi bi-arrow-left me-1"></i>' . $lang->global['back'] . '
            </a>
        </div>
    </div>
</nav>
';

// Основной контент
echo '
<div class="container py-4">
    <!-- Заголовок и поиск -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold gradient-text mb-3">
            <i class="bi bi-question-octagon me-2"></i>' . $lang->faq['faqtitle'] . '
        </h1>
        <p class="lead text-muted mb-4">
            ' . $lang->faq['faqdesc'] . '
        </p>
        
        <!-- Форма поиска -->
        <div class="search-box">
            <form method="get" action="' . $_SERVER['SCRIPT_NAME'] . '" class="input-group">
                <input type="hidden" name="do" value="search">
                <input type="text" 
                       name="words" 
                       class="form-control" 
                       placeholder="' . $lang->faq['searchplaceholder'] . '" 
                       value="' . htmlspecialchars($words, ENT_QUOTES, 'UTF-8') . '"
                       required>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search me-1"></i>' . $lang->faq['dosearch'] . '
                </button>
            </form>
        </div>
    </div>
    
    <div class="row">
        <!-- Основной контент -->
        <div class="col-lg-9">
';

// Обработка действий
if ($do === 'search' && !empty($words)) {
    if (strlen($words) < 3) {
        echo '
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ' . $lang->faq['searcherror'] . '
        </div>';
    } else {
        $results = $faqSystem->searchFAQ($words, $searchtype);
        
        if (empty($results)) {
            echo '
            <div class="text-center py-5">
                <i class="bi bi-search display-1 text-muted mb-4"></i>
                <h3 class="h4 fw-bold mb-3">' . $lang->faq['noresults'] . '</h3>
                <p class="text-muted">' . sprintf($lang->faq['noresultsfor'], htmlspecialchars($words, ENT_QUOTES, 'UTF-8')) . '</p>
                <a href="' . $_SERVER['SCRIPT_NAME'] . '" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>' . $lang->faq['backtofaq'] . '
                </a>
            </div>';
        } else {
            // Подсветка результатов поиска
            function highlightSearchTerms($text, $query) {
                return preg_replace(
                    '/(' . preg_quote($query, '/') . ')/i',
                    '<span class="highlight">$1</span>',
                    $text
                );
            }
            
            echo '
            <div class="mb-4">
                <h3 class="h4 fw-bold mb-3">' . $lang->faq['searchresults'] . '</h3>
                <p class="text-muted">' . sprintf($lang->faq['foundresults'], count($results), htmlspecialchars($words, ENT_QUOTES, 'UTF-8')) . '</p>
            </div>
            
            <div class="row g-4">';
            
            foreach ($results as $item) {
                $preview = strip_tags($item['description']);
                $preview = strlen($preview) > 150 ? substr($preview, 0, 150) . '...' : $preview;
                
                echo '
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title fw-bold">' . highlightSearchTerms($item['name'], $words) . '</h5>
                            <p class="card-text text-muted small">' . highlightSearchTerms($preview, $words) . '</p>
                            <a href="javascript:void(0)" onclick="showFaqAnswer(' . $item['id'] . ')" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>' . $lang->faq['viewanswer'] . '
                            </a>
                        </div>
                    </div>
                </div>';
            }
            
            echo '</div>';
        }
    }
} elseif ($view === 'category' && $id > 0) {
    $items = $faqSystem->getCategoryItems($id);
    
    if (empty($items)) {
        echo '
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ' . $lang->faq['noquestions'] . '
        </div>';
    } else {
        $categoryName = $items[0]['category_name'] ?? $lang->faq['category'];
        
        echo '
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="' . $_SERVER['SCRIPT_NAME'] . '"><i class="bi bi-house-door me-1"></i>' . $lang->faq['faqtitle'] . '</a></li>
                <li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') . '</li>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 fw-bold gradient-text">
                <i class="bi bi-folder me-2"></i>' . htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') . '
            </h2>
            <span class="stat-badge">' . count($items) . ' ' . $lang->faq['questions'] . '</span>
        </div>
        
        <div class="accordion" id="faqAccordion">';
        
        foreach ($items as $index => $item) {
            $icon = $item['icon_class'] ?? 'bi-question-circle';
            
            echo '
            <div class="accordion-item border mb-3">
                <div class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                            data-bs-target="#faq' . $item['id'] . '" aria-expanded="false">
                        <div class="d-flex align-items-center w-100">
                            <div class="me-3">
                                <i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' text-primary"></i>
                            </div>
                            <div class="text-start flex-grow-1">
                                <span class="fw-bold">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</span>
                            </div>
                        </div>
                    </button>
                </div>
                <div id="faq' . $item['id'] . '" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <div class="faq-content">' . $item['description'] . '</div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-eye me-1"></i> ' . ($item['views_count'] ?? 0) . ' ' . $lang->faq['views'] . '
                                </small>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="copyFaqLink(' . $item['id'] . ')">
                                        <i class="bi bi-link-45deg me-1"></i>' . $lang->faq['copylink'] . '
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
            
            // Увеличиваем счетчик просмотров
            $faqSystem->incrementViewCount($item['id']);
        }
        
        echo '</div>';
    }
} else {
    // Показать все категории
    $categories = $faqSystem->getCategories();
    
    if (empty($categories)) {
        echo '
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            ' . $lang->faq['nocategories'] . '
        </div>';
    } else {
        echo '<div class="row g-4">';
        
        foreach ($categories as $category) {
            // Получаем количество вопросов в категории
            $countResult = $db->sql_query("
                SELECT COUNT(*) as count 
                FROM faq 
                WHERE type = 'item' AND pid = " . (int)$category['id'] . " 
                AND is_active = 1
            ");
           
			
			
			 $countRow = $db->fetch_array($countResult);
    $count = $countRow['count'] ?? 0;
    $db->free_result($countResult);
			
			
			
			
			
            
            $icon = $category['icon_class'] ?? 'bi-folder';
            
            echo '
            <div class="col-lg-6">
                <div class="category-card card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="category-icon me-3">
                                <i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' fs-4 text-white"></i>
                            </div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">' . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . '</h3>
                                <p class="text-muted small mb-0">' . htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                ' . $count . ' ' . $lang->faq['questions'] . '
                            </span>
                            <a href="?view=category&id=' . $category['id'] . '" class="btn btn-outline-primary btn-sm">
                                ' . $lang->faq['browse'] . ' <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>';
        }
        
        echo '</div>';
    }
}

echo '
        </div>
        
        <!-- Сайдбар -->
        <div class="col-lg-3">
            <!-- Популярные вопросы -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-fire text-warning me-2"></i>' . $lang->faq['popular'] . '
                    </h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">';
                    
$popular = $faqSystem->getPopularFAQ(5);
foreach ($popular as $item) {
    echo '
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="javascript:void(0)" onclick="showFaqAnswer(' . $item['id'] . ')" class="text-decoration-none text-dark small">
                                ' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '
                            </a>
                            <span class="badge bg-warning bg-opacity-10 text-warning small">
                                ' . $item['views_count'] . '
                            </span>
                        </li>';
}

echo '
                    </ul>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-bar-chart me-2"></i>' . $lang->faq['stats'] . '
                    </h5>
                </div>
                <div class="card-body">';

$stats = $faqSystem->getStats();
echo '
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">' . $lang->faq['totalquestions'] . '</span>
                        <span class="fw-bold text-primary">' . $stats['total_questions'] . '</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">' . $lang->faq['totalcategories'] . '</span>
                        <span class="fw-bold text-success">' . $stats['total_categories'] . '</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">' . $lang->faq['totalviews'] . '</span>
                        <span class="fw-bold text-warning">' . number_format($stats['total_views']) . '</span>
                    </div>';

echo '
                </div>
            </div>
            
            <!-- Быстрые ссылки -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-lightning me-2"></i>' . $lang->faq['quicklinks'] . '
                    </h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <a href="/contact.php" class="text-decoration-none text-dark">
                                <i class="bi bi-envelope me-2 text-primary"></i>' . $lang->faq['contactsupport'] . '
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="/forum.php" class="text-decoration-none text-dark">
                                <i class="bi bi-chat-dots me-2 text-success"></i>' . $lang->faq['communityforum'] . '
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="/help.php" class="text-decoration-none text-dark">
                                <i class="bi bi-life-preserver me-2 text-info"></i>' . $lang->faq['helpcenter'] . '
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Кастомные скрипты -->
<script>
function copyFaqLink(faqId) {
    const link = window.location.origin + window.location.pathname + \'?view=item&id=\' + faqId;
    navigator.clipboard.writeText(link).then(() => {
        alert(\'' . $lang->faq['linkcopied'] . '\');
    });
}

function showFaqAnswer(faqId) {
    window.location.href = \'?view=item&id=\' + faqId;
}

// Автоматическое раскрытие при прямом просмотре
document.addEventListener(\'DOMContentLoaded\', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const faqId = urlParams.get(\'id\');
    const view = urlParams.get(\'view\');
    
    if (view === \'item\' && faqId) {
        const element = document.getElementById(\'faq\' + faqId);
        if (element) {
            const collapse = new bootstrap.Collapse(element);
            collapse.show();
            
            // Прокрутка к элементу
            element.scrollIntoView({ behavior: \'smooth\' });
        }
    }
    
    // Валидация формы поиска
    const searchForm = document.querySelector(\'form[action*="search"]\');
    if (searchForm) {
        searchForm.addEventListener(\'submit\', function(e) {
            const input = this.querySelector(\'input[name="words"]\');
            if (input.value.trim().length < 3) {
                e.preventDefault();
                alert(\'' . $lang->faq['searchminchars'] . '\');
                input.focus();
            }
        });
    }
});
</script>
';

// Завершаем вывод
stdfoot();