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



function menu($selected = '') {
    global $usergroups, $_this_script_, $_this_script_no_act, $BASEURL, $lang;

    $m_title      = htmlspecialchars($lang->staffpanel['menu_title']);
    $m_online     = htmlspecialchars($lang->staffpanel['menu_online']);
    $m_welcome    = htmlspecialchars($lang->staffpanel['menu_welcome']);
    $m_stafftools = htmlspecialchars($lang->staffpanel['menu_stafftools']);
    $m_manage     = htmlspecialchars($lang->staffpanel['menu_manage']);
    $m_newtool    = htmlspecialchars($lang->staffpanel['menu_newtool']);
    $m_security   = htmlspecialchars($lang->staffpanel['menu_security']);
    $m_settings   = htmlspecialchars($lang->staffpanel['menu_settings']);

    print '

	<div class="container mt-3">

	<div class="admin-menu-wrapper light-theme white-bg">
        <div class="menu-background"></div>
        <div class="menu-container">
            <div class="menu-header stat-card">
                <div class="menu-title">
                    <i class="fas fa-tachometer-alt blue-icon"></i>
                    <span>' . $m_title . '</span>
                </div>
                <div class="menu-status">
                    <span class="status-dot"></span>
                    <span>' . $m_online . '</span>
                </div>
            </div>

            <nav class="menu-nav">
                <ul class="menu-list">
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'welcome' ? 'active' : '') . '" href="' . $_this_script_no_act . '">
                            <div class="menu-icon">
                                <i class="fas fa-home blue-icon"></i>
                            </div>
                            <span class="menu-text">' . $m_welcome . '</span>
                            <div class="menu-badge">🏠</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'stafftools' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=stafftools">
                            <div class="menu-icon">
                                <i class="fas fa-users-cog blue-icon"></i>
                            </div>
                            <span class="menu-text">' . $m_stafftools . '</span>
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
                            <span class="menu-text">' . $m_manage . '</span>
                            <div class="menu-badge">🔧</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a class="menu-link stat-card ' . (($_GET['do'] ?? '') == 'newtool' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=managestafftools&do=newtool">
                            <div class="menu-icon">
                                <i class="fas fa-plus-square blue-icon"></i>
                            </div>
                            <span class="menu-text">' . $m_newtool . '</span>
                            <div class="menu-badge">➕</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'securitycheck' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=securitycheck">
                            <div class="menu-icon">
                                <i class="fas fa-shield-alt blue-icon"></i>
                            </div>
                            <span class="menu-text">' . $m_security . '</span>
                            <div class="menu-badge security">🔒</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'settings' ? 'active' : '') . '" href="managesettings.php">
                            <div class="menu-icon">
                                <i class="fas fa-cogs blue-icon"></i>
                            </div>
                            <span class="menu-text">' . $m_settings . '</span>
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

    <link rel="stylesheet" href="' . $BASEURL . '/admin/templates/admin-menu.css">
    ';
}




function get_list()
{
    global $thispath;
    global $_this_script_no_act;
    global $CURUSER;
    global $eol;
    global $db;
	global $BASEURL;
    global $lang;

    $query = $db->sql_query_prepared(
        'SELECT * FROM staffpanel WHERE usergroups LIKE ? ORDER BY name',
        ['%[' . intval($CURUSER['usergroup']) . ']%']
    );

    $str = '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/get-list.css">' . $eol;

    $count = 0;
    $tools_html = '';
    $categories = [];

    // Внутренний ключ категории (data-category, фильтр) остаётся английским,
    // переводится только подпись.
    $cat_labels = [
        'Users'         => $lang->staffpanel['cat_users'],
        'Moderation'    => $lang->staffpanel['cat_moderation'],
        'Torrents'      => $lang->staffpanel['cat_torrents'],
        'Forums'        => $lang->staffpanel['cat_forums'],
        'Content'       => $lang->staffpanel['cat_content'],
        'System'        => $lang->staffpanel['cat_system'],
        'Statistics'    => $lang->staffpanel['cat_statistics'],
        'Communication' => $lang->staffpanel['cat_communication'],
        'Maintenance'   => $lang->staffpanel['cat_maintenance'],
        'Tools'         => $lang->staffpanel['cat_tools'],
    ];

    $user_level   = htmlspecialchars((string) $CURUSER['usergroup']);
    $t_tool_badge = htmlspecialchars($lang->staffpanel['tool_badge']);
    $t_access_lvl = htmlspecialchars(ags_fmt($lang->staffpanel['tool_access_level'], (string) $CURUSER['usergroup']));

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

    while ($query && ($tools = $db->fetch_array($query)))
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
            $category_label = htmlspecialchars($cat_labels[$category] ?? $category);
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
                                <span class="tool-badge"><i class="fas fa-shield-alt"></i> ' . $t_tool_badge . '</span>
                            </div>
                            <div class="tool-category">
                                <i class="fas fa-folder"></i> ' . $category_label . '
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
                            <span>' . $t_access_lvl . '</span>
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
                    <i class="fas fa-shield-alt"></i> ' . htmlspecialchars($lang->staffpanel['hero_badge']) . '
                </div>
                <h1 class="hero-title">
                    <i class="fas fa-toolbox"></i>
                    ' . htmlspecialchars($lang->staffpanel['hero_title']) . '
                </h1>
                <p class="hero-subtitle">
                    ' . ags_fmt(htmlspecialchars($lang->staffpanel['hero_subtitle']), htmlspecialchars($CURUSER['username'])) . '
                </p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <i class="fas fa-calendar"></i>
                        <span>' . date($lang->staffpanel['fmt_date_long']) . '</span>
                    </div>
                    <div class="hero-stat">
                        <i class="fas fa-clock"></i>
                        <span>' . date($lang->staffpanel['fmt_time_short']) . '</span>
                    </div>
                    <div class="hero-stat">
                        <i class="fas fa-user-shield"></i>
                        <span>' . htmlspecialchars(ags_fmt($lang->staffpanel['hero_level'], $user_level)) . '</span>
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
                <input type="text" id="toolSearch" placeholder="' . htmlspecialchars($lang->staffpanel['ph_search_tools'], ENT_QUOTES) . '" onkeyup="filterTools()">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterByCategory(\'all\')">
                    <i class="fas fa-th-large"></i> ' . htmlspecialchars($lang->staffpanel['btn_filter_all']) . '
                </button>';

        foreach (array_keys($categories) as $cat) {
            $str .= '<button class="filter-btn" onclick="filterByCategory(\'' . htmlspecialchars($cat) . '\')">
                        <i class="fas fa-folder"></i> ' . htmlspecialchars($cat_labels[$cat] ?? $cat) . '
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
                    <div class="stat-label">' . htmlspecialchars($lang->staffpanel['stat_available_tools']) . '</div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-check-circle"></i> ' . htmlspecialchars($lang->staffpanel['stat_active']) . '
                </div>
            </div>

            <div class="stat-card" style="--stat-color: var(--success)">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value">' . count($categories) . '</div>
                    <div class="stat-label">' . htmlspecialchars($lang->staffpanel['stat_categories']) . '</div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-tags"></i> ' . htmlspecialchars($lang->staffpanel['stat_organized']) . '
                </div>
            </div>

            <div class="stat-card" style="--stat-color: var(--warning)">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">' . htmlspecialchars($lang->staffpanel['stat_system_status']) . '</div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-heartbeat"></i> ' . htmlspecialchars($lang->staffpanel['stat_operational']) . '
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
                <i class="fas fa-lock"></i> ' . htmlspecialchars($lang->staffpanel['empty_title']) . '
            </div>
            <div class="empty-text">
                ' . htmlspecialchars($lang->staffpanel['empty_text']) . '
            </div>
            <a href="#" class="empty-help" onclick="alert(window.staffT ? staffT(\'contact_admin\', \'Contact administrator for access\') : \'Contact administrator for access\')">
                <i class="fas fa-headset"></i> ' . htmlspecialchars($lang->staffpanel['empty_request']) . '
            </a>
        </div>';
    }

    $str .= '
    </div>

    <!-- Тостовое уведомление -->
    <div id="toast" class="toast-notification">
        <i class="fas fa-info-circle"></i>
        <span>' . htmlspecialchars($lang->staffpanel['toast_opened']) . '</span>
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
	global $BASEURL;
    global $lang;

    $query = $db->sql_query_prepared('SELECT * FROM staffpanel ORDER BY name');

    $str = '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/get-list2.css">' . $eol;

    $count = 0;
    $tools_html = '';

    $t_access  = htmlspecialchars($lang->staffpanel['lbl_access']);
    $t_active  = htmlspecialchars($lang->staffpanel['lbl_status_active']);
    $t_edit    = htmlspecialchars($lang->staffpanel['tip_edit_tool'], ENT_QUOTES);
    $t_delete  = htmlspecialchars($lang->staffpanel['tip_delete_tool'], ENT_QUOTES);

    while ($query && ($tools = $db->fetch_array($query)))
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
                            <div class="groups-label">' . $t_access . '</div>
                            <div class="groups-list" title="' . htmlspecialchars($usergroups) . '">' . htmlspecialchars($usergroups) . '</div>
                        </div>
                    </div>

                    <div class="tool-meta">
                        <div class="tool-status">
                            <span class="status-dot"></span>
                            <span>' . $t_active . '</span>
                        </div>
                        <div class="tool-actions">
                            <a class="tool-btn edit" href="' . $_this_script_ . '&do=edit&id=' . $tools['id'] . '" title="' . $t_edit . '" aria-label="' . $t_edit . '">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a class="tool-btn delete" href="' . $_this_script_ . '&do=delete&id=' . $tools['id'] . '" onclick="return confirm(window.staffT ? staffT(\'confirm_delete_tool\', \'Delete this tool?\') : \'Delete this tool?\')" title="' . $t_delete . '" aria-label="' . $t_delete . '">
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
                ' . htmlspecialchars($lang->staffpanel['list_title']) . '
            </h2>
        </div>

        <div class="tools-grid">' . $tools_html . '</div>

        <div class="tools-footer">
            <div class="tools-count">
                ' . ags_fmt(htmlspecialchars($lang->staffpanel['tools_count']), '<span class="count-number">' . $count . '</span>') . '
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
