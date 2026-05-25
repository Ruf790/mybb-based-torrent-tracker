<?php

function _access_check_ ()
{
    global $usergroups;
    if ($usergroups['cansettingspanel'] != 1)
    {
      print_no_permission (true);
      exit ();
      return null;
    }

}
  
function _form_header_open_ ($text, $colspan = 4)
{
    echo '
	
	<div class="container mt-3">
	<table align="center" border="0" class="tborder" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table class="tback" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="thead" colspan="' . $colspan . '" align="center">' . $text . '</td></tr>';
}
  
  

function _form_header_close_ ()
{
    echo '</table></tbody></td></tr></table></tbody></div>';
}


function menu($selected = '') {
    global $usergroups, $_this_script_, $_this_script_no_act;
    
    print '
    
	<div class="container mt-3">
	
	<div class="admin-menu-wrapper light-theme white-bg">
        <div class="menu-background"></div>
        <div class="menu-container">
            <div class="menu-header stat-card">
                <div class="menu-title">
                    <i class="fas fa-tachometer-alt blue-icon"></i>
                    <span>Admin Control Panel</span>
                </div>
                <div class="menu-status">
                    <span class="status-dot"></span>
                    <span>Online</span>
                </div>
            </div>
            
            <nav class="menu-nav">
                <ul class="menu-list">
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'welcome' ? 'active' : '') . '" href="' . $_this_script_no_act . '">
                            <div class="menu-icon">
                                <i class="fas fa-home blue-icon"></i>
                            </div>
                            <span class="menu-text">Welcome</span>
                            <div class="menu-badge">🏠</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'stafftools' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=stafftools">
                            <div class="menu-icon">
                                <i class="fas fa-users-cog blue-icon"></i>
                            </div>
                            <span class="menu-text">Staff Tools</span>
                            <div class="menu-badge">👥</div>
                        </a>
                    </li>';

    if ($usergroups['cansettingspanel'] == 1) {
        print '
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'managestafftools' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=managestafftools">
                            <div class="menu-icon">
                                <i class="fas fa-wrench blue-icon"></i>
                            </div>
                            <span class="menu-text">Manage Staff Tools</span>
                            <div class="menu-badge">🔧</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . (($_GET['do'] ?? '') == 'newtool' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=managestafftools&do=newtool">
                            <div class="menu-icon">
                                <i class="fas fa-plus-square blue-icon"></i>
                            </div>
                            <span class="menu-text">Add New Tool</span>
                            <div class="menu-badge">➕</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'securitycheck' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=securitycheck">
                            <div class="menu-icon">
                                <i class="fas fa-shield-alt blue-icon"></i>
                            </div>
                            <span class="menu-text">Security Console</span>
                            <div class="menu-badge security">🔒</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'settings' ? 'active' : '') . '" href="settings.php">
                            <div class="menu-icon">
                                <i class="fas fa-cogs blue-icon"></i>
                            </div>
                            <span class="menu-text">Tracker Settings</span>
                            <div class="menu-badge">⚙️</div>
                        </a>
                    </li>';
    }
    
    print '
                </ul>
            </nav>
            
            
        </div>
    </div>
	 </div>

    <style>
    /* Синие иконки */
    .blue-icon {
        color: #3b82f6 !important;
        transition: all 0.3s ease;
    }
    
    /* Базовые стили для белого фона */
    .admin-menu-wrapper.light-theme.white-bg {
        position: relative;
        margin: 30px 0;
        background: #ffffff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        min-height: 120px;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    .admin-menu-wrapper.light-theme.white-bg .menu-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 20% 80%, rgba(59,130,246,0.03) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(37,99,235,0.03) 0%, transparent 50%);
        animation: backgroundFloat 8s ease-in-out infinite;
        opacity: 0.6;
    }
    
    @keyframes backgroundFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(0.5deg); }
    }
    
    /* Стили для stat-card */
    .admin-menu-wrapper .stat-card {
        padding: 1.25rem;
        border-radius: 12px;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(226,232,240,0.5);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        display: flex;
        align-items: center;
    }
    
    .admin-menu-wrapper .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(59,130,246,0.15);
        background: rgba(255,255,255,0.85);
        border-color: rgba(59,130,246,0.2);
    }
    
    /* Hover эффект для синих иконок */
    .admin-menu-wrapper .stat-card:hover .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 8px rgba(59,130,246,0.4));
        transform: scale(1.1);
    }
    
    /* Активное состояние */
    .admin-menu-wrapper .stat-card.active {
        background: rgba(59,130,246,0.08);
        border-color: rgba(59,130,246,0.3);
        box-shadow: 0 5px 20px rgba(59,130,246,0.15);
        transform: translateY(-2px);
    }
    
    .admin-menu-wrapper .stat-card.active::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        border-radius: 0 0 12px 12px;
    }
    
    .admin-menu-wrapper .stat-card.active .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 10px rgba(59,130,246,0.6));
    }
    
    /* Health-stat для футера */
    .admin-menu-wrapper .health-stat {
        padding: 0.75rem 1rem;
        border-radius: 10px;
        background: rgba(0,0,0,0.03);
        border: 1px solid rgba(226,232,240,0.5);
        transition: all 0.3s ease;
        margin-top: auto;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .admin-menu-wrapper .health-stat:hover {
        background: rgba(59,130,246,0.05);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59,130,246,0.1);
        border-color: rgba(59,130,246,0.2);
    }
    
    .admin-menu-wrapper .health-stat:hover .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 5px rgba(59,130,246,0.3));
    }
    
    /* Хедер меню */
    .admin-menu-wrapper .menu-header.stat-card {
        margin-bottom: 20px;
        justify-content: space-between;
        background: rgba(255,255,255,0.9);
        border-radius: 15px;
    }
    
    .admin-menu-wrapper .menu-header.stat-card:hover {
        transform: none;
        box-shadow: 0 5px 15px rgba(59,130,246,0.1);
    }
    
    .admin-menu-wrapper .menu-header.stat-card:hover .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 6px rgba(59,130,246,0.3));
    }
    
    /* Список меню */
    .admin-menu-wrapper .menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .admin-menu-wrapper .menu-item {
        flex: 1;
        min-width: 200px;
    }
    
    /* Иконки в карточках с синим фоном */
    .admin-menu-wrapper .menu-icon {
        width: 40px;
        height: 40px;
        background: rgba(59,130,246,0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
        border: 1px solid rgba(59,130,246,0.2);
        transition: all 0.3s ease;
    }
    
    .admin-menu-wrapper .stat-card:hover .menu-icon {
        background: rgba(59,130,246,0.15);
        transform: scale(1.05);
        border-color: rgba(59,130,246,0.3);
        box-shadow: 0 4px 12px rgba(59,130,246,0.15);
    }
    
    .admin-menu-wrapper .stat-card.active .menu-icon {
        background: rgba(59,130,246,0.2);
        border-color: rgba(59,130,246,0.4);
    }
    
    /* Текст в карточках */
    .admin-menu-wrapper .menu-text {
        flex: 1;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.95em;
    }
    
    .admin-menu-wrapper .stat-card:hover .menu-text {
        color: #3b82f6;
    }
    
    .admin-menu-wrapper .stat-card.active .menu-text {
        color: #1d4ed8;
        font-weight: 700;
    }
    
    /* Бейджи */
    .admin-menu-wrapper .menu-badge {
        width: 28px;
        height: 28px;
        background: rgba(248,250,252,0.8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8em;
        color: #64748b;
        border: 1px solid rgba(148,163,184,0.3);
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .admin-menu-wrapper .menu-badge.security {
        background: rgba(59,130,246,0.1);
        color: #3b82f6;
        border-color: rgba(59,130,246,0.2);
    }
    
    .admin-menu-wrapper .stat-card:hover .menu-badge {
        transform: scale(1.2);
        background: #3b82f6;
        color: white;
        box-shadow: 0 3px 10px rgba(59,130,246,0.3);
        border-color: rgba(59,130,246,0.5);
    }
    
    /* Элементы хедера */
    .admin-menu-wrapper .menu-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.3em;
        font-weight: 700;
        color: #1e293b;
    }
    
    .admin-menu-wrapper .menu-title .blue-icon {
        font-size: 1.6em;
        background: rgba(59,130,246,0.1);
        padding: 10px;
        border-radius: 12px;
        border: 1px solid rgba(59,130,246,0.2);
    }
    
    .admin-menu-wrapper .menu-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9em;
        font-weight: 500;
        color: #059669;
    }
    
    .admin-menu-wrapper .status-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulseDot 2s infinite;
    }
    
    @keyframes pulseDot {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.2); }
        100% { opacity: 1; transform: scale(1); }
    }
    
    .admin-menu-wrapper .footer-info {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #64748b;
        font-size: 0.85em;
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .admin-menu-wrapper.light-theme.white-bg {
            margin: 15px;
            border-radius: 15px;
        }
        
        .admin-menu-wrapper .menu-list {
            flex-direction: column;
            gap: 10px;
        }
        
        .admin-menu-wrapper .menu-item {
            min-width: auto;
        }
        
        .admin-menu-wrapper .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .admin-menu-wrapper .stat-card:hover .blue-icon {
            transform: scale(1.05);
        }
    }
    
    /* Анимация появления */
    .admin-menu-wrapper {
        animation: menuSlideIn 0.6s ease-out;
    }
    
    @keyframes menuSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
    ';
}




function get_list()
{
    global $thispath;
    global $_this_script_no_act;
    global $CURUSER;
    global $eol;
    global $db;
    
    $query = $db->sql_query('SELECT * FROM staffpanel WHERE usergroups LIKE \'%[' . intval($CURUSER['usergroup']) . ']%\' ORDER BY name');
    
    $str = '
    <style type="text/css">
   
    
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --secondary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --dark: #0f172a;
        --light: #f8fafc;
        --gray: #64748b;
        --glass: rgba(255, 255, 255, 0.95);
        --glass-dark: rgba(15, 23, 42, 0.95);
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    }
	
	
    /* Анимации */
    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(5deg); }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
   
    /* Hero секция */

/* Hero секция с primary bg */
.staff-hero {
    background: var(--primary);
    border-radius: 28px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
}

/* Декоративные элементы поверх фона */
.staff-hero::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -20%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 8s ease-in-out infinite;
}

.staff-hero::after {
    content: "";
    position: absolute;
    bottom: -30%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite reverse;
}

.hero-content {
    position: relative;
    z-index: 2;
    color: white;
}

.hero-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
    letter-spacing: 0.5px;
}

.hero-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.hero-title i {
    margin-right: 12px;
}

.hero-subtitle {
    font-size: 1rem;
    opacity: 0.95;
    margin: 0;
}

.hero-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.hero-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 8px 20px;
    border-radius: 100px;
}

.hero-stat i {
    font-size: 1.2rem;
}



    
    /* Поиск и фильтры */
    .staff-controls {
        background: white;
        border-radius: 20px;
        padding: 1rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow);
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }
    
    .search-box {
        flex: 1;
        min-width: 200px;
        position: relative;
    }
    
    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
    }
    
    .search-box input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
    }
    
    .search-box input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    
    .filter-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e2e8f0;
        background: white;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .filter-btn:hover, .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        transform: translateY(-2px);
    }
    
    /* Сетка инструментов */
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    /* Карточка инструмента */
    .tool-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        box-shadow: var(--shadow);
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
    }
    
    .tool-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-xl);
    }
    
    .tool-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }
    
    .tool-card:hover::before {
        transform: scaleX(1);
    }
    
    .tool-bg-pattern {
        position: absolute;
        top: 0;
        right: 0;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(99,102,241,0.05) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(30%, -30%);
    }
    
    .tool-content {
        padding: 1.5rem;
        position: relative;
        z-index: 2;
    }
    
    .tool-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .tool-icon {
        width: 60px;
        height: 60px;
        background: var(--icon-bg);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .tool-icon::before {
        content: "";
        position: absolute;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 100%);
        transform: translateX(-100%);
        transition: transform 0.4s ease;
    }
    
    .tool-card:hover .tool-icon::before {
        transform: translateX(100%);
    }
    
    .tool-card:hover .tool-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .tool-info {
        flex: 1;
    }
    
    .tool-name {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--dark);
        margin: 0 0 0.25rem 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .tool-badge {
        display: inline-block;
        background: var(--icon-color);
        color: white;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .tool-category {
        font-size: 0.7rem;
        color: var(--icon-color);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .tool-description {
        color: var(--gray);
        font-size: 0.85rem;
        line-height: 1.5;
        margin: 0.75rem 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .tool-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
    }
    
    .tool-access {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.7rem;
        color: var(--gray);
    }
    
    .tool-access i {
        font-size: 0.65rem;
    }
    
    .tool-link {
        width: 36px;
        height: 36px;
        background: #f8fafc;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--icon-color);
        transition: all 0.3s ease;
    }
    
    .tool-card:hover .tool-link {
        background: var(--icon-color);
        color: white;
        transform: translateX(4px);
    }
    
    /* Цветовые схемы */
    .tool-card.blue {
        --icon-color: #3b82f6;
        --icon-bg: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    }
    
    .tool-card.green {
        --icon-color: #10b981;
        --icon-bg: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    }
    
    .tool-card.purple {
        --icon-color: #8b5cf6;
        --icon-bg: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
    }
    
    .tool-card.orange {
        --icon-color: #f59e0b;
        --icon-bg: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    }
    
    .tool-card.red {
        --icon-color: #ef4444;
        --icon-bg: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    }
    
    .tool-card.pink {
        --icon-color: #ec4899;
        --icon-bg: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
    }
    
    .tool-card.cyan {
        --icon-color: #06b6d4;
        --icon-bg: linear-gradient(135deg, #ecfeff 0%, #cffafe 100%);
    }
    
    .tool-card.indigo {
        --icon-color: #6366f1;
        --icon-bg: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    }
    
    /* Статистика */
    .stats-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        box-shadow: var(--shadow);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--stat-color, var(--primary));
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover::before {
        transform: scaleX(1);
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .stat-icon {
        width: 55px;
        height: 55px;
        background: linear-gradient(135deg, var(--stat-color, var(--primary)), var(--stat-color, var(--primary-dark)));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: white;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--dark);
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
        color: var(--gray);
        font-weight: 500;
    }
    
    .stat-trend {
        font-size: 0.75rem;
        color: var(--success);
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    /* Пустое состояние */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 28px;
        box-shadow: var(--shadow-lg);
    }
    
    .empty-animation {
        width: 120px;
        height: 120px;
        margin: 0 auto 1.5rem;
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pulse 2s ease-in-out infinite;
    }
    
    .empty-animation i {
        font-size: 3rem;
        color: #94a3b8;
    }
    
    .empty-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }
    
    .empty-text {
        color: var(--gray);
        margin-bottom: 1.5rem;
    }
    
    .empty-help {
        display: inline-block;
        padding: 10px 24px;
        background: var(--primary);
        color: white;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .empty-help:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    /* Тосты уведомления */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--dark);
        color: white;
        padding: 12px 20px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        box-shadow: var(--shadow-lg);
    }
    
    .toast-notification.show {
        transform: translateX(0);
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .staff-dashboard {
            padding: 12px;
        }
        
        .hero-title {
            font-size: 1.5rem;
        }
        
        .hero-stats {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .hero-stat {
            justify-content: center;
        }
        
        .tools-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .staff-controls {
            flex-direction: column;
        }
        
        .search-box {
            width: 100%;
        }
        
        .filter-buttons {
            width: 100%;
            justify-content: center;
        }
        
        .tool-header {
            flex-direction: column;
            text-align: center;
        }
        
        .tool-icon {
            margin: 0 auto;
        }
        
        .tool-name {
            justify-content: center;
        }
        
        .tool-category {
            text-align: center;
            display: block;
        }
        
        .tool-meta {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        
        .tool-link {
            margin: 0 auto;
        }
    }
    
    /* Скроллбар */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }
    </style>' . $eol;
    
    $count = 0;
    $tools_html = '';
    $categories = [];
    
    // Расширенный массив иконок
    $tool_icons = [
        'user' => ['icon' => 'fas fa-users', 'color' => 'green', 'category' => 'Users'],
        'users' => ['icon' => 'fas fa-users', 'color' => 'green', 'category' => 'Users'],
        'profile' => ['icon' => 'fas fa-user-circle', 'color' => 'blue', 'category' => 'Users'],
        'ban' => ['icon' => 'fas fa-gavel', 'color' => 'red', 'category' => 'Moderation'],
        'block' => ['icon' => 'fas fa-ban', 'color' => 'red', 'category' => 'Moderation'],
        'torrent' => ['icon' => 'fas fa-magnet', 'color' => 'purple', 'category' => 'Torrents'],
        'upload' => ['icon' => 'fas fa-cloud-upload-alt', 'color' => 'purple', 'category' => 'Torrents'],
        'download' => ['icon' => 'fas fa-download', 'color' => 'purple', 'category' => 'Torrents'],
        'forum' => ['icon' => 'fas fa-comments', 'color' => 'blue', 'category' => 'Forums'],
        'topic' => ['icon' => 'fas fa-comment-dots', 'color' => 'blue', 'category' => 'Forums'],
        'post' => ['icon' => 'fas fa-reply', 'color' => 'blue', 'category' => 'Forums'],
        'news' => ['icon' => 'fas fa-newspaper', 'color' => 'cyan', 'category' => 'Content'],
        'announce' => ['icon' => 'fas fa-bullhorn', 'color' => 'orange', 'category' => 'Content'],
        'backup' => ['icon' => 'fas fa-database', 'color' => 'indigo', 'category' => 'System'],
        'log' => ['icon' => 'fas fa-history', 'color' => 'purple', 'category' => 'System'],
        'logs' => ['icon' => 'fas fa-history', 'color' => 'purple', 'category' => 'System'],
        'config' => ['icon' => 'fas fa-cog', 'color' => 'blue', 'category' => 'System'],
        'setting' => ['icon' => 'fas fa-sliders-h', 'color' => 'blue', 'category' => 'System'],
        'report' => ['icon' => 'fas fa-flag', 'color' => 'red', 'category' => 'Moderation'],
        'reports' => ['icon' => 'fas fa-flag', 'color' => 'red', 'category' => 'Moderation'],
        'statistic' => ['icon' => 'fas fa-chart-line', 'color' => 'green', 'category' => 'Statistics'],
        'stats' => ['icon' => 'fas fa-chart-bar', 'color' => 'green', 'category' => 'Statistics'],
        'mail' => ['icon' => 'fas fa-envelope', 'color' => 'purple', 'category' => 'Communication'],
        'message' => ['icon' => 'fas fa-envelope', 'color' => 'purple', 'category' => 'Communication'],
        'clean' => ['icon' => 'fas fa-broom', 'color' => 'orange', 'category' => 'Maintenance'],
        'maintain' => ['icon' => 'fas fa-wrench', 'color' => 'orange', 'category' => 'Maintenance'],
        'default' => ['icon' => 'fas fa-toolbox', 'color' => 'blue', 'category' => 'Tools']
    ];
    
    while ($tools = $db->fetch_array($query))
    {
        $usergroups = explode(',', $tools['usergroups']);
        if (((@file_exists($thispath . $tools['filename']) AND strstr($tools['usergroups'], '[' . $CURUSER['usergroup'] . ']')) AND in_array('[' . $CURUSER['usergroup'] . ']', $usergroups, true)))
        {
            // Определение иконки
            $icon_data = $tool_icons['default'];
            foreach ($tool_icons as $key => $data) {
                if ($key !== 'default' && stripos($tools['name'], $key) !== false) {
                    $icon_data = $data;
                    break;
                }
            }
            
            $icon = $icon_data['icon'];
            $color_class = $icon_data['color'];
            $category = $icon_data['category'];
            $display_name = htmlspecialchars(ucwords(str_replace('_', ' ', $tools['name'])));
            $short_desc = strlen($tools['description']) > 100 ? substr($tools['description'], 0, 100) . '...' : $tools['description'];
            
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            
            $tools_html .= '
            <div class="tool-card ' . $color_class . '" data-category="' . htmlspecialchars($category) . '" data-name="' . strtolower($display_name) . '" style="animation-delay: ' . ($count * 0.05) . 's">
                <div class="tool-bg-pattern"></div>
                <div class="tool-content" onclick="window.location.href=\'' . $_this_script_no_act . '?act=' . $tools['name'] . '\'">
                    <div class="tool-header">
                        <div class="tool-icon">
                            <i class="' . $icon . '"></i>
                        </div>
                        <div class="tool-info">
                            <div class="tool-name">
                                ' . $display_name . '
                                <span class="tool-badge"><i class="fas fa-shield-alt"></i> STAFF</span>
                            </div>
                            <div class="tool-category">
                                <i class="fas fa-folder"></i> ' . $category . '
                            </div>
                        </div>
                    </div>
                    <p class="tool-description">
                        <i class="fas fa-quote-left" style="font-size: 0.7rem; opacity: 0.5; margin-right: 0.25rem;"></i>
                        ' . htmlspecialchars($short_desc) . '
                    </p>
                    <div class="tool-meta">
                        <div class="tool-access">
                            <i class="fas fa-key"></i>
                            <span>Access Level ' . htmlspecialchars($CURUSER['usergroup']) . '</span>
                            <i class="fas fa-circle" style="font-size: 0.3rem; color: ' . $icon_data['color'] . ';"></i>
                            <span><i class="fas fa-clock"></i> ' . date('H:i') . '</span>
                        </div>
                        <div class="tool-link">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </div>' . $eol;
            
            ++$count;
        }
    }
    
    // Основная разметка
     $str .= '
    <div class="staff-dashboard">
        <!-- Hero секция -->
        <div class="staff-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-shield-alt"></i> STAFF CONTROL PANEL
                </div>
                <h1 class="hero-title">
                    <i class="fas fa-toolbox"></i>
                    Admin Dashboard
                </h1>
                <p class="hero-subtitle">
                    Welcome back, ' . htmlspecialchars($CURUSER['username']) . '! Manage your community with powerful tools.
                </p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <i class="fas fa-calendar"></i>
                        <span>' . date('l, F j, Y') . '</span>
                    </div>
                    <div class="hero-stat">
                        <i class="fas fa-clock"></i>
                        <span>' . date('g:i A') . '</span>
                    </div>
                    <div class="hero-stat">
                        <i class="fas fa-user-shield"></i>
                        <span>Level ' . htmlspecialchars($CURUSER['usergroup']) . '</span>
                    </div>
                </div>
            </div>
        </div>';
    
    if ($count > 0) 
    {
        $str .= '
        <!-- Поиск и фильтры -->
        <div class="staff-controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="toolSearch" placeholder="Search tools by name or description..." onkeyup="filterTools()">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterByCategory(\'all\')">
                    <i class="fas fa-th-large"></i> All
                </button>';
        
        foreach (array_keys($categories) as $cat) {
            $str .= '<button class="filter-btn" onclick="filterByCategory(\'' . htmlspecialchars($cat) . '\')">
                        <i class="fas fa-folder"></i> ' . htmlspecialchars($cat) . '
                    </button>';
        }
        
        $str .= '
            </div>
        </div>
        
        <!-- Сетка инструментов -->
        <div class="tools-grid" id="toolsGrid">
            ' . $tools_html . '
        </div>
        
        <!-- Статистика -->
        <div class="stats-section">
            <div class="stat-card" style="--stat-color: var(--primary)">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value">' . $count . '</div>
                    <div class="stat-label">Available Tools</div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-check-circle"></i> Active
                </div>
            </div>
            
            <div class="stat-card" style="--stat-color: var(--success)">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value">' . count($categories) . '</div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-tags"></i> Organized
                </div>
            </div>
            
            <div class="stat-card" style="--stat-color: var(--warning)">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">System Status</div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-heartbeat"></i> Operational
                </div>
            </div>
        </div>';
        
        // JavaScript для фильтрации
        $str .= '
        <script>
        function filterTools() {
            const searchTerm = document.getElementById("toolSearch").value.toLowerCase();
            const cards = document.querySelectorAll(".tool-card");
            
            cards.forEach(card => {
                const name = card.getAttribute("data-name");
                const desc = card.querySelector(".tool-description").innerText.toLowerCase();
                const matches = name.includes(searchTerm) || desc.includes(searchTerm);
                card.style.display = matches ? "" : "none";
            });
        }
        
        function filterByCategory(category) {
            const cards = document.querySelectorAll(".tool-card");
            const buttons = document.querySelectorAll(".filter-btn");
            
            buttons.forEach(btn => btn.classList.remove("active"));
            event.target.classList.add("active");
            
            if (category === "all") {
                cards.forEach(card => card.style.display = "");
            } else {
                cards.forEach(card => {
                    const cardCategory = card.getAttribute("data-category");
                    card.style.display = cardCategory === category ? "" : "none";
                });
            }
        }
        
        // Показываем уведомление при клике на карточку (опционально)
        document.querySelectorAll(".tool-card").forEach(card => {
            card.addEventListener("click", function(e) {
                if(e.target.tagName !== "A" && !e.target.closest("a")) {
                    console.log("Opening tool:", this.querySelector(".tool-name").innerText);
                }
            });
        });
        </script>';
    } 
    else 
    {
        $str .= '
        <div class="empty-state">
            <div class="empty-animation">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="empty-title">
                <i class="fas fa-lock"></i> Access Restricted
            </div>
            <div class="empty-text">
                You don\'t have permission to access any staff tools at this moment.
            </div>
            <a href="#" class="empty-help" onclick="alert(\'Contact administrator for access\')">
                <i class="fas fa-headset"></i> Request Access
            </a>
        </div>';
    }
    
    $str .= '
    </div>
    
    <!-- Тостовое уведомление -->
    <div id="toast" class="toast-notification">
        <i class="fas fa-info-circle"></i>
        <span>Tool opened successfully!</span>
    </div>
    
    <script>
    function showToast(message) {
        const toast = document.getElementById("toast");
        toast.querySelector("span").innerText = message;
        toast.classList.add("show");
        setTimeout(() => {
            toast.classList.remove("show");
        }, 3000);
    }
    </script>' . $eol;
    
    echo $str;
}












function get_list2()
{
    global $thispath;
    global $_this_script_;
    global $_this_script_no_act;
    global $eol;
    global $db;
    
    $query = $db->sql_query('SELECT * FROM staffpanel ORDER BY name');
    
    $str = '
    <style type="text/css">
    .admin-tools-table {
        width: 100%;
        background: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        border: 1px solid #f1f5f9;
        margin: 16px 0;
    }
    
    .tools-header {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .tools-title {
        font-size: 1.3em;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .tools-title i {
        color: #3b82f6;
        font-size: 1.2em;
    }
    
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 0;
    }
    
    .tool-row {
        display: contents;
    }
    
    .tool-item {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
        background: #ffffff;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .tool-item:nth-child(odd) {
        border-right: 1px solid #f1f5f9;
    }
    
    .tool-item:hover {
        background: #fafeff;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(59,130,246,0.06);
    }
    
    .tool-header-info {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
    }
    
    .tool-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 1px solid #e0f2fe;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        font-size: 1em;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    
    .tool-item:hover .tool-icon {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        color: white;
        transform: scale(1.05);
    }
    
    .tool-info {
        flex: 1;
        min-width: 0;
    }
    
    .tool-name {
        font-size: 1em;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 4px 0;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .tool-description {
        color: #64748b;
        font-size: 0.8em;
        line-height: 1.3;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .tool-groups {
        background: #f8fafc;
        padding: 8px 10px;
        border-radius: 8px;
        margin: 8px 0;
        border: 1px solid #e2e8f0;
    }
    
    .groups-label {
        font-size: 0.7em;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 4px;
    }
    
    .groups-list {
        font-size: 0.8em;
        color: #1e293b;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .tool-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 8px;
    }
    
    .tool-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.7em;
        font-weight: 500;
        color: #059669;
        background: #ecfdf5;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #d1fae5;
    }
    
    .status-dot {
        width: 6px;
        height: 6px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    .tool-actions {
        display: flex;
        gap: 6px;
    }
    
    .tool-btn {
        padding: 6px 10px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75em;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        text-decoration: none;
        min-width: 50px;
    }
    
    .tool-btn.edit {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        color: white;
        box-shadow: 0 1px 4px rgba(59,130,246,0.3);
    }
    
    .tool-btn.edit:hover {
        background: linear-gradient(135deg, #1d4ed8, #3b82f6);
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(59,130,246,0.4);
    }
    
    .tool-btn.delete {
        background: linear-gradient(135deg, #ef4444, #f87171);
        color: white;
        box-shadow: 0 1px 4px rgba(239,68,68,0.3);
    }
    
    .tool-btn.delete:hover {
        background: linear-gradient(135deg, #dc2626, #ef4444);
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(239,68,68,0.4);
    }
    
    .tools-footer {
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        text-align: center;
    }
    
    .tools-count {
        font-size: 0.9em;
        font-weight: 600;
        color: #475569;
    }
    
    .count-number {
        color: #3b82f6;
        font-weight: 800;
        font-size: 1.1em;
    }
    
    /* Адаптивность */
    @media (max-width: 1024px) {
        .tools-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .admin-tools-table {
            border-radius: 12px;
            margin: 12px 0;
        }
        
        .tools-header {
            padding: 16px;
        }
        
        .tools-title {
            font-size: 1.1em;
        }
        
        .tool-item {
            padding: 14px;
            min-height: 160px;
        }
        
        .tool-header-info {
            flex-direction: row;
            text-align: left;
            gap: 10px;
        }
        
        .tool-item:nth-child(odd) {
            border-right: none;
        }
        
        .tools-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .tool-header-info {
            flex-direction: column;
            text-align: center;
            gap: 8px;
        }
        
        .tool-icon {
            align-self: center;
        }
        
        .tool-meta {
            flex-direction: column;
            gap: 8px;
            align-items: stretch;
        }
        
        .tool-actions {
            justify-content: center;
        }
    }
    
    /* Анимация появления */
    .tool-item {
        animation: slideInUp 0.4s ease-out;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Задержка для последовательной анимации */
    .tool-item:nth-child(2n) {
        animation-delay: 0.1s;
    }
    </style>' . $eol;
    
    $count = 0;
    $tools_html = '';
    
    while ($tools = $db->fetch_array($query))
    {
        if (@file_exists($thispath . $tools['filename']))
        {
            $usergroups = str_replace(array('[', ']'), '', $tools['usergroups']);
            $icon = get_tool_icon_admin($tools['name']);
            
            $tools_html .= '
            <div class="tool-row">
                <div class="tool-item">
                    <div>
                        <div class="tool-header-info">
                            <div class="tool-icon">
                                <i class="' . $icon . '"></i>
                            </div>
                            <div class="tool-info">
                                <h3 class="tool-name" title="' . htmlspecialchars($tools['name']) . '">' . htmlspecialchars(strtoupper($tools['name'])) . '</h3>
                                <p class="tool-description" title="' . htmlspecialchars($tools['description']) . '">' . htmlspecialchars($tools['description']) . '</p>
                            </div>
                        </div>
                        
                        <div class="tool-groups">
                            <div class="groups-label">Access</div>
                            <div class="groups-list" title="' . htmlspecialchars($usergroups) . '">' . htmlspecialchars($usergroups) . '</div>
                        </div>
                    </div>
                    
                    <div class="tool-meta">
                        <div class="tool-status">
                            <span class="status-dot"></span>
                            <span>Active</span>
                        </div>
                        <div class="tool-actions">
                            <a class="tool-btn edit" href="' . $_this_script_ . '&do=edit&id=' . $tools['id'] . '" title="Edit Tool">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a class="tool-btn delete" href="' . $_this_script_ . '&do=delete&id=' . $tools['id'] . '" onclick="return confirm(\'Delete this tool?\')" title="Delete Tool">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>' . $eol;
            
            ++$count;
        }
    }
    
    $str .= '
    <div class="admin-tools-table">
        <div class="tools-header">
            <h2 class="tools-title">
                <i class="fas fa-cogs"></i>
                Staff Tools
            </h2>
        </div>
        
        <div class="tools-grid">' . $tools_html . '</div>
        
        <div class="tools-footer">
            <div class="tools-count">
                <span class="count-number">' . $count . '</span> tools available
            </div>
        </div>
    </div>' . $eol;
    
    echo $str;
}

// Функция для получения иконок инструментов (остается без изменений)
function get_tool_icon_admin($tool_name) {
    $tool_lower = strtolower($tool_name);
    
    $icon_map = [
        'user' => 'fas fa-users',
        'search' => 'fas fa-search',
        'stats' => 'fas fa-chart-bar',
        'log' => 'fas fa-clipboard-list',
        'settings' => 'fas fa-cogs',
        'security' => 'fas fa-shield-alt',
        'backup' => 'fas fa-database',
        'mail' => 'fas fa-envelope',
        'report' => 'fas fa-flag',
        'moderate' => 'fas fa-gavel',
        'torrent' => 'fas fa-download',
        'forum' => 'fas fa-comments',
        'system' => 'fas fa-server',
        'debug' => 'fas fa-bug',
        'test' => 'fas fa-vial',
        'money' => 'fas fa-coins',
        'news' => 'fas fa-newspaper',
        'announce' => 'fas fa-bullhorn'
    ];
    
    foreach ($icon_map as $key => $icon) {
        if (strpos($tool_lower, $key) !== false) {
            return $icon;
        }
    }
    
    return 'fas fa-toolbox';
}
