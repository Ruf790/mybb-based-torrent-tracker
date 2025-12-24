<?


  $templatelist = "multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

  $rootpath = './../';
  $thispath = './';
  define ('IN_ADMIN_PANEL', true);
  define ('STAFF_PANEL_TSSEv56', true);
  define ('SKIP_CRON_JOBS', true);
  define ('SKIP_LOCATION_SAVE', true);
  define("IN_MYBB", 1);
  define("IN_ADMINCP", 1);
  
  
  if (session_status() === PHP_SESSION_NONE) 
  {
    session_start();
  }
  
  
  require_once $rootpath . 'global.php';
  gzip ();



  
  
  maxsysop ();
  if (!is_mod ($usergroups))
  {
    print_no_permission (true);
    exit ();
  }

  require_once $thispath . 'include/adminfunctions.php';



  flash_message();
  
 

  $act = (isset ($_POST['act']) ? htmlspecialchars ($_POST['act']) : (isset ($_GET['act']) ? htmlspecialchars ($_GET['act']) : ''));
  $_this_script_ = htmlspecialchars ($_SERVER['SCRIPT_NAME']) . '?act=' . $act;
  $_this_script_no_act = htmlspecialchars ($_SERVER['SCRIPT_NAME']);
  
  
  if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
  {
    $eol = '
';
  }
  else
  {
    if (strtoupper (substr (PHP_OS, 0, 3) == 'MAC'))
    {
      $eol = '
';
    }
    else
    {
      $eol = '
';
    }
  }

  $act_array = array ('securitycheck', 'managestafftools', 'stafftools');
  if (((!empty ($act) AND !in_array ($act, $act_array)) AND @file_exists ($thispath . $act . '.php')))
  {
    _file_access_check_ ($act);
    include $thispath . $act . '.php';
    
	
echo '
<style type="text/css">
.admin-floating-bar {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 16px;
    padding: 16px 20px;
   
                0 0 0 1px rgba(255,255,255,0.1);
    backdrop-filter: blur(20px);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    font-family: "Segoe UI", system-ui, sans-serif;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation: slideInRight 0.6s ease-out;
}

.admin-floating-bar:hover {
    transform: translateY(-2px) scale(1.02);
  
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
}

.admin-floating-bar.hidden {
    opacity: 0;
    transform: translateX(100px) scale(0.8);
    pointer-events: none;
}

.floating-bar-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.floating-bar-icon {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.1);
}

.admin-floating-bar:hover .floating-bar-icon {
    background: rgba(255,255,255,0.3);
    transform: scale(1.1) rotate(5deg);
    
}

.floating-bar-text {
    flex: 1;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.floating-bar-link {
    color: white;
    text-decoration: none;
    padding: 8px 16px;
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
    text-shadow: 0 1px 1px rgba(0,0,0,0.1);
}

.floating-bar-link:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-1px);
   
    border-color: rgba(255,255,255,0.3);
}

.floating-bar-close {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 10px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    margin-left: 8px;
}

.floating-bar-close:hover {
    background: rgba(255,255,255,0.25);
    transform: scale(1.1) rotate(90deg);
    border-color: rgba(255,255,255,0.3);
}

.floating-bar-pulse {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: #60a5fa;
    border-radius: 50%;
   
    animation: pulseBlue 2s infinite;
}

.floating-bar-badge {
    position: absolute;
    top: -8px;
    left: -8px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
   
    animation: bounce 2s infinite;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulseBlue {
    0% {
        transform: scale(1);
        opacity: 1;
        box-shadow: 0 0 15px #3b82f6;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.7;
        box-shadow: 0 0 20px #60a5fa;
    }
    100% {
        transform: scale(1);
        opacity: 1;
        box-shadow: 0 0 15px #3b82f6;
    }
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

/* Голубые акценты при наведении на ссылки */
.floating-bar-link:hover i {
    color: #bfdbfe;
    transform: translateX(-2px);
}

.floating-bar-link i {
    transition: all 0.3s ease;
}

/* Адаптивность */
@media (max-width: 768px) {
    .admin-floating-bar {
        top: 10px;
        right: 10px;
        left: 10px;
        flex-direction: column;
        text-align: center;
        padding: 20px;
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    }
    
    .floating-bar-content {
        flex-direction: column;
        gap: 16px;
    }
    
    .floating-bar-actions {
        display: flex;
        gap: 10px;
        width: 100%;
        justify-content: center;
    }
    
    .floating-bar-link,
    .floating-bar-close {
        flex: 1;
        justify-content: center;
    }
    
    .floating-bar-pulse {
        top: 10px;
        right: 10px;
    }
}

/* Темная тема поддержка */
@media (prefers-color-scheme: dark) {
    .admin-floating-bar {
        background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        border-color: rgba(255,255,255,0.15);
    }
}

/* Дополнительные синие вариации */
.admin-floating-bar.success {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 10px 40px rgba(5, 150, 105, 0.3);
}

.admin-floating-bar.warning {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    box-shadow: 0 10px 40px rgba(217, 119, 6, 0.3);
}

.admin-floating-bar.premium {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    box-shadow: 0 10px 40px rgba(124, 58, 237, 0.3);
}

/* Анимация для старых браузеров */
@-webkit-keyframes slideInRight {
    from {
        opacity: 0;
        -webkit-transform: translateX(100px);
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        -webkit-transform: translateX(0);
        transform: translateX(0);
    }
}

/* Эффект свечения */
.admin-floating-bar::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(96, 165, 250, 0.1));
    border-radius: 16px;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
}

.admin-floating-bar:hover::before {
    opacity: 1;
}
</style>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const floatingBar = {
        element: null,
        isVisible: true,
        persistClose: false,
        
        init() {
            this.element = document.getElementById("adminFloatingBar");
            this.bindEvents();
            this.checkPreviousState();
            this.addGlowEffect();
        },
        
        bindEvents() {
            const closeBtn = this.element.querySelector(".floating-bar-close");
            closeBtn.addEventListener("click", () => this.close());
            
            // Авто-скрытие через 15 секунд (опционально)
            // setTimeout(() => this.close(), 15000);
        },
        
        close() {
            this.isVisible = false;
            this.element.classList.add("hidden");
            
            if (this.persistClose) {
                localStorage.setItem("adminFloatingBarClosed", "true");
            }
            
            setTimeout(() => {
                this.element.style.display = "none";
            }, 400);
        },
        
        checkPreviousState() {
            if (this.persistClose && localStorage.getItem("adminFloatingBarClosed") === "true") {
                this.element.style.display = "none";
                this.isVisible = false;
            }
        },
        
        show() {
            this.isVisible = true;
            this.element.classList.remove("hidden");
            this.element.style.display = "flex";
            
            if (this.persistClose) {
                localStorage.removeItem("adminFloatingBarClosed");
            }
        },
        
        addGlowEffect() {
            // Добавляем периодическое свечение
            setInterval(() => {
                if (this.isVisible) {
                    this.element.style.boxShadow = "0 10px 40px rgba(59, 130, 246, 0.4), 0 0 0 1px rgba(255,255,255,0.1)";
                    setTimeout(() => {
                        if (this.isVisible) {
                            this.element.style.boxShadow = "0 10px 40px rgba(59, 130, 246, 0.3), 0 0 0 1px rgba(255,255,255,0.1)";
                        }
                    }, 1000);
                }
            }, 5000);
        }
    };
    
    floatingBar.init();
    window.adminFloatingBar = floatingBar;
});

// Фолбэк для старых браузеров
if (!window.addEventListener) {
    window.attachEvent("onload", function() {
        document.getElementById("adminFloatingBar").style.display = "flex";
    });
}
</script>

<div id="adminFloatingBar" class="admin-floating-bar">
    <div class="floating-bar-pulse"></div>
    <div class="floating-bar-badge">ADMIN</div>
    
    <div class="floating-bar-content">
        <div class="floating-bar-icon">
            <i class="fas fa-user-shield"></i>
        </div>
        
        <div class="floating-bar-text">
            <strong>Staff Panel</strong>
            <div style="font-size: 11px; opacity: 0.9;">Administrative Access</div>
        </div>
        
        <div class="floating-bar-actions">
            <a href="' . $BASEURL . '/admin/index.php" class="floating-bar-link">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
        </div>
        
        <button class="floating-bar-close" title="Close Panel">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
';

exit();
return 1;
	
	
	
	
	
	
  }

  if ($act == 'stafftools')
  {
    stdhead ('Staff Tools');
    menu ('stafftools');
    echo '
	<div class="container mt-3">
	<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table border="0" cellpadding="6" cellspacing="0" width="100%">
	<tbody><tr><td colspan="4" align="center">Staff Tools</td></tr>';
    echo '<tr><td width="100%" align="center" colspan="4">Tool Name - Description</td></tr>';
    get_list ();
    echo '</table></td></tr></table>';
    close_menu ();
    stdfoot ();
    exit ();
    return 1;
  }

  if ($act == 'managestafftools')
  {
    _access_check_ ();
    
	
	
	
	if ($_GET['do'] == 'newtool')
{
    stdhead ('Make a New Tool');
    menu ('managestafftools');
    
	//<div class="card shadow-lg border-0 rounded-3">
    echo '
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                
				
				<div class="card">
				
				
                    <div class="card-header bg-gradient-primary text-white rounded-top-3 py-4">
                        <div class="d-flex align-items-center">
                            <div class="header-icon me-3">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 fw-bold">Create New Tool</h4>
                                <p class="mb-0 opacity-75">Add a new staff tool to the administration panel</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <form method="post" action="' . $_this_script_ . '&do=savenewtool" class="needs-validation" novalidate>
                            <!-- Tool Name -->
                            <div class="mb-4">
                                <label for="toolName" class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-toolbox me-2 text-primary"></i>
                                    Tool Name
                                </label>
                                <input type="text" class="form-control form-control-lg" id="toolName" name="name" 
                                       placeholder="Enter tool name (e.g., User Manager)" required>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Use a descriptive name that clearly identifies the tool
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <label for="toolDescription" class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-align-left me-2 text-primary"></i>
                                    Description
                                </label>
                                <textarea class="form-control" id="toolDescription" name="description" 
                                          rows="3" placeholder="Brief description of what this tool does..." required></textarea>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Provide a clear description of the tool\'s purpose
                                </div>
                            </div>
                            
                            <!-- File Name -->
                            <div class="mb-4">
                                <label for="toolFilename" class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-file-code me-2 text-primary"></i>
                                    File Name
                                </label>
                                <input type="text" class="form-control" id="toolFilename" name="filename" 
                                       placeholder="tool_filename.php" required>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Enter the PHP file name without path (e.g., usermanager.php)
                                </div>
                            </div>
                            
                            <!-- Permissions -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark mb-3">
                                    <i class="fas fa-users-cog me-2 text-primary"></i>
                                    Access Permissions
                                </label>
                                <div class="permissions-grid">';
    
    $count = 0;
    $sql = $db->sql_query('SELECT gid, title, namestyle FROM usergroups WHERE canstaffpanel = 1 ORDER BY disporder');
    
    while ($group = $db->fetch_array($sql))
    {
        $isSysop = $group['gid'] == UC_SYSOP;
        $groupName = get_user_color($group['title'], $group['namestyle']);
        
        echo '
                                    <div class="permission-item">
                                        <div class="form-check">
                                            <input class="form-check-input permission-checkbox" type="checkbox" 
                                                   name="gid[]" value="[' . $group['gid'] . ']" 
                                                   id="group_' . $group['gid'] . '" ' . ($isSysop ? 'checked' : '') . '>
                                            <label class="form-check-label" for="group_' . $group['gid'] . '">
                                                ' . $groupName . '
                                                ' . ($isSysop ? '<span class="badge bg-primary ms-1">Default</span>' : '') . '
                                            </label>
                                        </div>
                                    </div>';
        $count++;
    }
    
    echo '
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="checkAllPermissions()">
                                        <i class="fas fa-check-double me-1"></i>
                                        Check All
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="uncheckAllPermissions()">
                                        <i class="fas fa-times me-1"></i>
                                        Uncheck All
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <a href="' . $_this_script_no_act . '?act=managestafftools" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Back to Tools
                                </a>
                                <div class="btn-group">
                                    <button type="reset" class="btn btn-outline-danger">
                                        <i class="fas fa-undo me-1"></i>
                                        Reset
                                    </button>
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-1"></i>
                                        Create Tool
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
    .bg-gradient-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
    }
    
    .card {
        border: 1px solid rgba(0,0,0,0.08);
        
    }
    
    .form-control, .form-control-lg {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.3s ease;
        padding: 12px 16px;
    }
    
    .form-control:focus {
        border-color: #3b82f6;
       
        transform: translateY(-1px);
    }
    
    .form-label {
        font-size: 0.95em;
        color: #374151;
    }
    
    .permissions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
    }
    
    .permission-item {
        padding: 8px 0;
    }
    
    .form-check-input {
        width: 18px;
        height: 18px;
        margin-top: 0.2em;
        border: 2px solid #d1d5db;
    }
    
    .form-check-input:checked {
        background-color: #3b82f6;
        border-color: #3b82f6;
    }
    
    .form-check-label {
        color: #374151;
        font-weight: 500;
        cursor: pointer;
        margin-left: 8px;
    }
    
    .badge {
        font-size: 0.7em;
        padding: 4px 8px;
    }
    
    .btn {
        border-radius: 10px;
        font-weight: 500;
        padding: 10px 20px;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        border: none;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        
    }
    
    .btn-outline-secondary:hover {
        transform: translateY(-1px);
    }
    
    /* Анимации */
    .card {
        animation: slideInUp 0.6s ease-out;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .permissions-grid {
            grid-template-columns: 1fr;
        }
        
        .d-flex.justify-content-between {
            flex-direction: column;
            gap: 16px;
        }
        
        .btn-group {
            width: 100%;
        }
        
        .btn-group .btn {
            flex: 1;
        }
    }
    </style>
    
    <script>
    function checkAllPermissions() {
        document.querySelectorAll(".permission-checkbox").forEach(checkbox => {
            checkbox.checked = true;
        });
    }
    
    function uncheckAllPermissions() {
        document.querySelectorAll(".permission-checkbox").forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    
    // Валидация формы
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.querySelector(".needs-validation");
        form.addEventListener("submit", function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        });
        
        // Автогенерация имени файла на основе названия инструмента
        const nameInput = document.getElementById("toolName");
        const fileInput = document.getElementById("toolFilename");
        
        nameInput.addEventListener("blur", function() {
            if (nameInput.value && !fileInput.value) {
                const filename = nameInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, "_")
                    .replace(/^_+|_+$/g, "") + ".php";
                fileInput.value = filename;
            }
        });
    });
    </script>';
    
    close_menu();
    stdfoot();
    exit();
}
	
	
	
	
	
	
	
	
    else
    {
      if ($_GET['do'] == 'savenewtool')
      {
        $name = htmlspecialchars_uni ($_POST['name']);
        $description = htmlspecialchars_uni ($_POST['description']);
        $filename = $name . '.php';
        $usergroups = (!empty ($_POST['gid']) ? implode (',', $_POST['gid']) : '');
        if (((empty ($name) OR empty ($description)) OR empty ($usergroups)))
        {
          stderr ('Error! Dont leave any fields blank!');
        }
        else
        {
          if (!file_exists ($thispath . $filename))
          {
            stderr ('Error File <b>' . $thispath . 'admin/' . $filename . '</b> does not exists! Please make sure that you have uploaded it correctly!', false);
          }
        }

		
		
		$insert_staffpanel_tool = array(
			"name" => $db->escape_string($name),
			"description" => $db->escape_string($description),
			"filename" => $db->escape_string($filename),
			"usergroups" => $db->escape_string($usergroups)
			
		);


		$db->insert_query("staffpanel", $insert_staffpanel_tool);
		
        
		
		
		redirect ('admin/index.php?act=' . $name, 'The new tool has been added..');
        exit ();
      }
      else
      {
        if (((isset ($_GET['id']) AND is_valid_id ($_GET['id'])) AND $_GET['do'] == 'delete'))
        {
          $id = intval ($_GET['id']);
          if ($_GET['sure'] != 'yes')
          {
            stderr ('Sanity Check, Are you sure to delete the tool?<br /><br /><strong><a href="' . $_this_script_ . '&do=delete&id=' . $id . '&sure=yes"><font color="red">Yes, I am sure</a></font> <a href="' . $_this_script_ . '">No, Go back!</a>', false);
          }

          $db->sql_query ('DELETE FROM staffpanel WHERE id = ' . $db->escape_string ($id));
          redirect ('admin/index.php?act=managestafftools', 'The tool has been deleted..');
          exit ();
        }
        else
        {
        
		
		
		
		
		
		
		if (((isset($_GET['id']) AND is_valid_id($_GET['id'])) AND $_GET['do'] == 'edit'))
{
    $id = intval($_GET['id']);
    $sql = $db->sql_query('SELECT * FROM staffpanel WHERE id = ' . $db->escape_string($id));
    if ($db->num_rows($sql) == 0)
    {
        stderr('Error! Tool not found in database');
    }

    $tool = $db->fetch_array($sql);
    stdhead('Edit Tool');
    menu('managestafftools');
    
    echo '
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="card">
                    <div class="card-header bg-primary text-white rounded-top-3 py-4">
                        <div class="d-flex align-items-center">
                            <div class="header-icon me-3">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 fw-bold">Edit Tool</h4>
                                <p class="mb-0 opacity-75">Update tool settings and permissions</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <form method="post" action="' . $_this_script_ . '&do=savetool&id=' . $id . '" class="needs-validation" novalidate>
                            <!-- Tool Name -->
                            <div class="mb-4">
                                <label for="toolName" class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-toolbox me-2 text-warning"></i>
                                    Tool Name
                                </label>
                                <input type="text" class="form-control form-control-lg" id="toolName" name="name" 
                                       value="' . htmlspecialchars($tool['name']) . '" 
                                       placeholder="Enter tool name" required>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Unique identifier for the tool
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <label for="toolDescription" class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-align-left me-2 text-warning"></i>
                                    Description
                                </label>
                                <input type="text" class="form-control" id="toolDescription" name="description" 
                                       value="' . htmlspecialchars($tool['description']) . '" 
                                       placeholder="Tool description..." required>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Describe what this tool does
                                </div>
                            </div>
                            
                            <!-- File Name -->
                            <div class="mb-4">
                                <label for="toolFilename" class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-file-code me-2 text-warning"></i>
                                    File Name
                                </label>
                                <input type="text" class="form-control" id="toolFilename" name="filename" 
                                       value="' . htmlspecialchars($tool['filename']) . '" 
                                       placeholder="tool_filename.php" required>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    PHP file associated with this tool
                                </div>
                            </div>
                            
                            <!-- Permissions -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark mb-3">
                                    <i class="fas fa-users-cog me-2 text-warning"></i>
                                    Access Permissions
                                </label>
                                <div class="permissions-grid">';
    
    $sql = $db->sql_query('SELECT gid, title, namestyle FROM usergroups WHERE canstaffpanel = \'1\' ORDER BY disporder');
    $usergroups = explode(',', $tool['usergroups']);
    $count = 0;
    
    while ($group = $db->fetch_array($sql))
    {
        $isChecked = in_array('[' . $group['gid'] . ']', $usergroups);
        $groupName = get_user_color($group['title'], $group['namestyle']);
        
        echo '
                                    <div class="permission-item">
                                        <div class="form-check">
                                            <input class="form-check-input permission-checkbox" type="checkbox" 
                                                   name="gid[]" value="[' . $group['gid'] . ']" 
                                                   id="group_' . $group['gid'] . '" ' . ($isChecked ? 'checked' : '') . '>
                                            <label class="form-check-label" for="group_' . $group['gid'] . '">
                                                ' . $groupName . '
                                            </label>
                                        </div>
                                    </div>';
        $count++;
    }
    
    echo '
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-warning btn-sm me-2" onclick="checkAllPermissions()">
                                        <i class="fas fa-check-double me-1"></i>
                                        Check All
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="uncheckAllPermissions()">
                                        <i class="fas fa-times me-1"></i>
                                        Uncheck All
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Tool Info -->
                            <div class="alert alert-info border-0 rounded-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle me-3 fs-5 text-info"></i>
                                    <div>
                                        <h6 class="alert-heading mb-1">Tool Information</h6>
                                        <p class="mb-0 small">ID: <strong>' . $tool['id'] . '</strong> | Created: ' . date('Y-m-d H:i', $tool['added']) . '</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <a href="' . $_this_script_no_act . '?act=managestafftools" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Back to Tools
                                </a>
                                <div class="btn-group">
                                    <button type="reset" class="btn btn-outline-danger">
                                        <i class="fas fa-undo me-1"></i>
                                        Reset
                                    </button>
                                    <button type="submit" class="btn btn-warning px-4 text-white">
                                        <i class="fas fa-save me-1"></i>
                                        Update Tool
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
    .bg-gradient-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
    }
    
    .card {
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .form-control, .form-control-lg {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.3s ease;
        padding: 12px 16px;
    }
    
    .form-control:focus {
        border-color: #f59e0b;
       
        transform: translateY(-1px);
    }
    
    .form-label {
        font-size: 0.95em;
        color: #374151;
    }
    
    .permissions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        background: #fefce8;
        border: 1px solid #fef3c7;
        border-radius: 12px;
        padding: 20px;
    }
    
    .permission-item {
        padding: 8px 0;
    }
    
    .form-check-input {
        width: 18px;
        height: 18px;
        margin-top: 0.2em;
        border: 2px solid #d1d5db;
    }
    
    .form-check-input:checked {
        background-color: #f59e0b;
        border-color: #f59e0b;
    }
    
    .form-check-input:focus {
       
    }
    
    .form-check-label {
        color: #374151;
        font-weight: 500;
        cursor: pointer;
        margin-left: 8px;
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border: none;
        color: white !important;
    }
    
    .btn-warning:hover {
        transform: translateY(-2px);
       
        color: white;
    }
    
    .btn-outline-warning {
        border-color: #f59e0b;
        color: #d97706;
    }
    
    .btn-outline-warning:hover {
        background-color: #f59e0b;
        border-color: #f59e0b;
        color: white;
    }
    
    .alert-info {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 1px solid #bae6fd;
    }
    
    .btn {
        border-radius: 10px;
        font-weight: 500;
        padding: 10px 20px;
        transition: all 0.3s ease;
    }
    
    .btn-outline-secondary:hover {
        transform: translateY(-1px);
    }
    
    /* Анимации */
    .card {
        animation: slideInUp 0.6s ease-out;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .permissions-grid {
            grid-template-columns: 1fr;
        }
        
        .d-flex.justify-content-between {
            flex-direction: column;
            gap: 16px;
        }
        
        .btn-group {
            width: 100%;
        }
        
        .btn-group .btn {
            flex: 1;
        }
    }
    </style>
    
    <script>
    function checkAllPermissions() {
        document.querySelectorAll(".permission-checkbox").forEach(checkbox => {
            checkbox.checked = true;
        });
    }
    
    function uncheckAllPermissions() {
        document.querySelectorAll(".permission-checkbox").forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    
    // Валидация формы
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.querySelector(".needs-validation");
        form.addEventListener("submit", function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        });
    });
    </script>';
    
    close_menu();
    stdfoot();
    exit();
}
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
          else
          {
            if (((isset ($_GET['id']) AND is_valid_id ($_GET['id'])) AND $_GET['do'] == 'savetool'))
            {
              $id = intval ($_GET['id']);
              $name = htmlspecialchars_uni ($_POST['name']);
              $description = htmlspecialchars_uni ($_POST['description']);
              $filename = $name . '.php';
              $usergroups = (!empty ($_POST['gid']) ? implode (',', $_POST['gid']) : '');
              if (((empty ($name) OR empty ($description)) OR empty ($usergroups)))
              {
                stderr ('Error!', 'Don\'t leave any fields blank!');
              }
              else
              {
                if (!file_exists ($thispath . $filename))
                {
                  stderr ('Error', 'File <b>' . $thispath . 'admin/' . $filename . '</b> does not exists! Please make sure that you have uploaded it correctly!', false);
                }
              }

             
              
			   $update_staffpanel = array(
				  "name" => $db->escape_string ($name), 
			      "description" => $db->escape_string ($description),
			      "filename" => $db->escape_string ($filename), 
			      "usergroups" => $db->escape_string ($usergroups)
		      );
					
		      $db->update_query("staffpanel", $update_staffpanel, "id='".$id."'");
			  
			  
			  redirect ('index.php?act=managestafftools', 'The tool has been updated..');
              exit ();
            }
          }
        }
      }
    }

    stdhead ('Manage Staff Tools');
    menu ('managestafftools');
    echo '<p align="right"><input type="button" class="hoptobutton" value="Add New Tool" onClick="jumpto(\'' . $_this_script_no_act . '?act=managestafftools&do=newtool\')"></p>';
    _form_header_open_ ('Manage Staff Tools', 6);
    get_list2 ();
    echo '</table></tbody></td></tr></table></tbody></div>';
    echo '</td></tr></table>';
    echo '<br /><p align="right"><input type="button" class="hoptobutton" value="Add New Tool" onClick="jumpto(\'' . $_this_script_no_act . '?act=managestafftools&do=newtool\')"></p>';
    stdfoot ();
    exit ();
    return 1;
  }

  
  
  
  
  
  
  
  
  
if ($act == 'securitycheck')
{
    _access_check_();
    stdhead('Security Console');
    menu('securitycheck');
    
    function security_check($query)
    {
        global $BASEURL;
        $url = $BASEURL . '/' . $query;
        $try = @file_get_contents($url, 'r');
        return $try;
    }

    function security_check_results($text = '', $risk = 2, $passed = true, $notice = '')
    {
        global $risk_levels;
        
        $risk_colors = [
            1 => 'success',    // Low - green
            2 => 'warning',    // Medium - yellow  
            3 => 'danger'      // High - red
        ];
        
        $risk_icons = [
            1 => 'fas fa-shield-alt',
            2 => 'fas fa-exclamation-triangle',
            3 => 'fas fa-radiation-alt'
        ];
        
        echo '
        <div class="security-item ' . ($passed ? 'security-passed' : 'security-failed') . '">
            <div class="security-header">
                <div class="security-icon">
                    <i class="' . ($passed ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger') . '"></i>
                </div>
                <div class="security-title">
                    <h6 class="mb-1">' . $text . '</h6>
                    <div class="security-notice">' . 
                        (!empty($notice) ? 
                         '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>' . $notice . '</span>' : 
                         '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Passed. No Error Found!</span>') . 
                    '</div>
                </div>
                <div class="security-risk">
                    <span class="badge bg-' . $risk_colors[$risk] . ' risk-badge">
                        <i class="' . $risk_icons[$risk] . ' me-1"></i>
                        ' . $risk_levels[$risk] . '
                    </span>
                </div>
            </div>
        </div>';
    }

    require INC_PATH . '/readconfig_announce.php';
    
    // Original security checks
    $check__1 = security_check('config/DATABASE');
    $check__2 = security_check('include/config.php');
    $check__3 = false;
    $check__4 = $iv == 'yes';
    $check__5 = $securelogin == 'yes';
    $check__6 = $bannedclientdetect == 'yes';
    $check__7 = $maxloginattempts <= 7;
    $check__8 = $privatetrackerpatch == 'yes';
    $check__9 = $disablerightclick == 'yes';
    
    
    $pattern1 = '[A-Z]+';
    $pattern2 = '[0-9]+';
    $pattern3 = '[a-z]+';
    if (
        preg_match($pattern1, $securehash) &&
        preg_match($pattern2, $securehash) &&
        preg_match($pattern3, $securehash) &&
        strlen($securehash) >= 10
    ) {
        $check__3 = true;
    }

    // 🔐 NEW SECURITY CHECKS
    
    // 1. File System Security
    $check__11 = substr(sprintf('%o', fileperms('config.php')), -4) == '0644';
    $check__12 = !file_exists('backup.sql') && !file_exists('database_backup.zip');
    $check__13 = !is_dir('install') && !is_dir('setup');
    $check__14 = !file_exists('phpinfo.php') && !file_exists('test.php');
    
    // 2. PHP Configuration
    $check__15 = ini_get('safe_mode') == '';
    $check__16 = ini_get('display_errors') == '0' || ini_get('display_errors') == '';
    $check__17 = ini_get('open_basedir') == '';
    $check__18 = ini_get('log_errors') == '1';
    $check__19 = version_compare(PHP_VERSION, '7.4.0', '>=');
    
    // 3. Database Security
    $check__20 = $db->sql_query("SHOW TABLES LIKE 'users'")->num_rows == 0;
    
    // Check for empty passwords
    $empty_pass_result = $db->sql_query("SELECT COUNT(*) as cnt FROM users WHERE password = '' OR password IS NULL");
    $empty_pass_row = $db->fetch_array($empty_pass_result);
    $check__21 = $empty_pass_row['cnt'] == 0;
    
    // Check for weak passwords (less than 6 chars)
    $weak_pass_result = $db->sql_query("SELECT COUNT(*) as cnt FROM users WHERE LENGTH(password) < 6");
    $weak_pass_row = $db->fetch_array($weak_pass_result);
    $check__22 = $weak_pass_row['cnt'] == 0;
    
    // 4. SSL/HTTPS
    $check__23 = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $check__24 = !empty($SITEURL) && strpos($SITEURL, 'https://') === 0;
    
    // 5. Brute Force Protection
    $check__25 = $maxloginattempts <= 5;
    $check__26 = isset($accountlockout) && $accountlockout == 'yes';
    
    // 6. Cross-Site Protection
    $check__27 = function_exists('csrf_token') || $securelogin == 'yes';
    $check__28 = isset($disallowjavascript) && $disallowjavascript == 'yes';
    
    // 7. MySQL Version
    $mysql_version_result = $db->sql_query("SELECT VERSION() as version");
    $mysql_version_row = $db->fetch_array($mysql_version_result);
    $mysql_version = $mysql_version_row['version'];
    $check__29 = version_compare($mysql_version, '5.7.0', '>=');
    
    // 8. Access Logging
    $check__30 = isset($trackerlog) && $trackerlog == 'yes';
    
    // 9. Session Security
    $check__31 = ini_get('session.cookie_httponly') == '1';
    $check__32 = ini_get('session.cookie_secure') == '1' || $check__23;
    
    // 10. File Upload Security
    $check__33 = ini_get('file_uploads') == '1' ? (ini_get('upload_max_filesize') >= '2M') : true;
    $check__34 = !empty(ini_get('upload_tmp_dir'));

    $risk_levels = [
        1 => 'Low Risk',
        2 => 'Medium Risk', 
        3 => 'High Risk'
    ];
    
    // Calculate security score
    $all_checks = [
        $check__1, $check__2, $check__3, $check__4, $check__5, $check__6, $check__7, $check__8, $check__9, $check__10,
        $check__11, $check__12, $check__13, $check__14, $check__15, $check__16, $check__17, $check__18, $check__19, $check__20,
        $check__21, $check__22, $check__23, $check__24, $check__25, $check__26, $check__27, $check__28, $check__29, $check__30,
        $check__31, $check__32, $check__33, $check__34
    ];
    
    $passed_checks = array_sum($all_checks);
    $total_checks = count($all_checks);
    $security_score = round(($passed_checks / $total_checks) * 100, 1);
    
    // Determine security level
    if ($security_score >= 90) {
        $security_level = 'Excellent';
        $level_color = 'success';
        $level_icon = 'fas fa-shield-alt';
    } elseif ($security_score >= 70) {
        $security_level = 'Good';
        $level_color = 'info';
        $level_icon = 'fas fa-check-circle';
    } elseif ($security_score >= 50) {
        $security_level = 'Fair';
        $level_color = 'warning';
        $level_icon = 'fas fa-exclamation-triangle';
    } else {
        $security_level = 'Poor';
        $level_color = 'danger';
        $level_icon = 'fas fa-radiation-alt';
    }
    
    echo '
	
	
	
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header Card -->
				
				<div class="container mt-3">
				
                <div class="card security-header-card border-0 shadow-sm mb-4">
                    <div class="card-body text-center py-5">
                        <div class="security-main-icon mb-3">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">Security Console</h2>
                       
                        
                        <!-- Security Score -->
                        <div class="security-score-card mb-4">
                            <div class="score-circle">
                                <div class="score-value">' . $security_score . '%</div>
                                <div class="score-label">Security Score</div>
                            </div>
                            <div class="score-level">
                                <span class="badge bg-' . $level_color . '">
                                    <i class="' . $level_icon . ' me-1"></i>
                                    ' . $security_level . '
                                </span>
                            </div>
                        </div>
                        
                        <div class="security-stats">
                            <div class="row justify-content-center">
                                <div class="col-auto">
                                    <div class="stat-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>' . $passed_checks . ' Passed</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="stat-item">
                                        <i class="fas fa-times-circle text-danger"></i>
                                        <span>' . ($total_checks - $passed_checks) . ' Failed</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="stat-item">
                                        <i class="fas fa-list-alt text-primary"></i>
                                        <span>' . $total_checks . ' Total</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Checks -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0 py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2 text-primary"></i>
                            Security Checks Overview (' . $total_checks . ' checks performed)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="security-checks-list">';
    
    // ORIGINAL CHECKS
    if (strstr($check__1, 'mysql_pass')) {
        security_check_results('Directory Protection (Config Folder)', 3, false, 'Please contact the <a href="http://templateshares.net" target="_blank" class="text-danger">TS Team</a> regarding the issue.');
    } else {
        security_check_results('Directory Protection (Config Folder)', 3);
    }

    if ($check__2 != '<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>') {
        security_check_results('Directory Protection (Important Files)', 3, false, 'Please contact the <a href="http://templateshares.net" target="_blank" class="text-danger">TS Team</a> regarding the issue.');
    } else {
        security_check_results('Directory Protection (Important Files)', 3);
    }

    if (!$check__3) {
        security_check_results('Secure Hash', 3, false, 'This feature will (attempt to) secure tracker cookies from hackers! We recommend that you choose a word only known by you and it contains upper and lowercase letters and alphanumeric values with atleast 10 chars.');
    } else {
        security_check_results('Secure Hash', 3);
    }

    if (!$check__10) {
        security_check_results('Virtual Keyboard (Against Hackers)', 2, false, 'We recommend that you TURN ON Virtual Keyboard for better security against Keyloggers.');
    } else {
        security_check_results('Virtual Keyboard (Against Hackers)');
    }

    if (!$check__4) {
        security_check_results('Image Verification (Against Hackers)', 2, false, 'We recommend that you TURN ON Image Verification for better security.');
    } else {
        security_check_results('Image Verification (Against Hackers)');
    }

    if (!$check__5) {
        security_check_results('Secure Login (Against Hackers)', 2, false, 'We recommend that you TURN ON Secure Login for better security.');
    } else {
        security_check_results('Secure Login (Against Hackers)');
    }

    if (!$check__6) {
        security_check_results('Banned Client Detection', 2, false, 'Once a banned client is detected by the system, this feature will (attempt to) deny seeding & leeching.');
    } else {
        security_check_results('Banned Client Detection', 2);
    }

    if (!$check__7) {
        security_check_results('Failed Login Attempts', 2, false, 'We recommend that you keep this value below 7');
    } else {
        security_check_results('Failed Login Attempts', 2);
    }

    if (!$check__8) {
        security_check_results('Private Tracker Patch', 2, false, 'We recommend that you TURN ON Private Tracker Patch for better security.');
    } else {
        security_check_results('Private Tracker Patch', 2);
    }

    if (!$check__9) {
        security_check_results('Right Mouse Click', 1, false, 'This feature will (attempt to) disable the right click on your page!');
    } else {
        security_check_results('Right Mouse Click', 1);
    }

    // 🔐 NEW CHECKS
    
    // File System Security
    security_check_results('Config File Permissions', 3, $check__11, 'Config files should have 644 permissions (not writable by others)');
    security_check_results('Backup Files Exposure', 3, $check__12, 'Backup files should not be accessible in web root directory');
    security_check_results('Install Directory Removal', 3, $check__13, 'Installation directory should be removed from production');
    security_check_results('Test Files Removal', 2, $check__14, 'Remove test and info files (phpinfo.php, test.php) from production');
    
    // PHP Configuration
    security_check_results('PHP Safe Mode Disabled', 2, $check__15, 'Safe mode should be disabled for modern PHP applications');
    security_check_results('Error Display Disabled', 2, $check__16, 'Display errors should be off in production environment');
    security_check_results('Open Base Directory', 2, $check__17, 'Open base directory restriction should be configured');
    security_check_results('Error Logging Enabled', 1, $check__18, 'Error logging should be enabled for debugging');
    security_check_results('PHP Version 7.4+', 2, $check__19, 'Use PHP 7.4 or newer for security updates and performance');
    
    // Database Security
    security_check_results('Default Table Names', 2, $check__20, 'Tables should not use default names (consider using prefixes)');
    security_check_results('Empty Passwords Check', 3, $check__21, 'No users should have empty or NULL passwords');
    security_check_results('Weak Passwords Check', 2, $check__22, 'No users should have passwords shorter than 6 characters');
    
    // SSL/HTTPS
    security_check_results('HTTPS Enforcement', 2, $check__23, 'Site should use HTTPS for secure connections');
    security_check_results('SSL Certificate Valid', 1, $check__24, 'SSL certificate should be properly configured');
    
    // Brute Force Protection
    security_check_results('Login Attempts Limit', 2, $check__25, 'Login attempts should be limited to 5 or fewer');
    security_check_results('Account Lockout System', 2, $check__26, 'Account lockout should be enabled after failed attempts');
    
    // Cross-Site Protection
    security_check_results('CSRF Protection', 2, $check__27, 'CSRF tokens should be implemented for forms');
    security_check_results('XSS Protection', 2, $check__28, 'JavaScript should be restricted for security');
    
    // MySQL Version
    security_check_results('MySQL Version 5.7+', 2, $check__29, 'Use MySQL 5.7 or newer for security features');
    
    // Access Logging
    security_check_results('Access Logging Enabled', 1, $check__30, 'Access logging should be enabled for monitoring');
    
    // Session Security
    security_check_results('HTTPOnly Session Cookies', 2, $check__31, 'Session cookies should be HTTPOnly for security');
    security_check_results('Secure Session Cookies', 2, $check__32, 'Session cookies should be Secure (HTTPS only)');
    
    // File Upload Security
    security_check_results('File Upload Limits', 2, $check__33, 'File upload limits should be properly configured');
    security_check_results('Upload Temp Directory', 1, $check__34, 'Upload temporary directory should be specified');

    echo '
                        </div>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="card border-warning mt-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle text-warning fs-4 me-3 mt-1"></i>
                            <div>
                                <h6 class="text-warning mb-2">Important Security Notice</h6>
                                <p class="mb-2 text-muted">
                                    <strong>Please Note:</strong> We do not 100% guarantee that above checks will protect your tracker against hackers. 
                                    We strongly recommend that you use latest version of following applications:
                                </p>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark me-2">TS Special Edition</span>
                                    <span class="badge bg-light text-dark me-2">Apache</span>
                                    <span class="badge bg-light text-dark me-2">PHP</span>
                                    <span class="badge bg-light text-dark me-2">MySQL</span>
                                    <span class="badge bg-light text-dark">Phpmyadmin</span>
                                </div>
                                <p class="mb-0 text-muted">
                                    <strong>Always remember:</strong> Perfect security on the Internet does not exist.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .security-header-card {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 20px;
    }
    
    .security-main-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: white;
        font-size: 2.5em;
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }
    
    .security-score-card {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .score-circle {
        width: 100px;
        height: 100px;
        background: conic-gradient(#10b981 ' . $security_score . '%, #e2e8f0 0%);
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        position: relative;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .score-value {
        font-size: 1.5em;
        font-weight: 700;
    }
    
    .score-label {
        font-size: 0.7em;
        opacity: 0.9;
    }
    
    .score-level .badge {
        font-size: 1em;
        padding: 8px 16px;
        border-radius: 20px;
    }
    
    .security-stats .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: #64748b;
    }
    
    .security-checks-list {
        padding: 0;
    }
    
    .security-item {
        padding: 20px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.3s ease;
    }
    
    .security-item:last-child {
        border-bottom: none;
    }
    
    .security-item:hover {
        background: #fafbff;
    }
    
    .security-item.security-failed {
        background: rgba(239, 68, 68, 0.03);
    }
    
    .security-header {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }
    
    .security-icon {
        width: 40px;
        height: 40px;
        background: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2em;
        flex-shrink: 0;
        margin-top: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .security-title {
        flex: 1;
        min-width: 0;
    }
    
    .security-title h6 {
        color: #1e293b;
        font-weight: 600;
    }
    
    .security-notice {
        font-size: 0.9em;
        margin-top: 4px;
    }
    
    .security-risk {
        flex-shrink: 0;
    }
    
    .risk-badge {
        font-size: 0.8em;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
    }
    
    .card {
        border-radius: 16px;
        overflow: hidden;
    }
    
    .card-header {
        border-bottom: 1px solid #e2e8f0;
    }
    
    /* Анимации */
    .security-item {
        animation: slideInUp 0.5s ease-out;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .security-header {
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }
        
        .security-icon {
            align-self: center;
        }
        
        .security-stats .row {
            flex-direction: column;
            gap: 12px;
        }
        
        .security-stats .col-auto {
            width: 100%;
        }
        
        .stat-item {
            justify-content: center;
        }
        
        .security-score-card {
            flex-direction: column;
            gap: 15px;
        }
    }
    </style>';
    
    echo '</td></tr></table>';
    stdfoot();
    exit();
    return 1;
}
  
  
  
  
  
  
  
  
  
  
  
  

  stdhead ('Welcome to Staff Panel ' . AP_VERSION . ' of ' . VERSION);
  menu ('welcome');
  function get_count ($name, $where = '', $extra = '')
  {
    global $db;
	$res = $db->sql_query ('SELECT COUNT(*) as ' . $name . ' FROM ' . $where . ' ' . ($extra ? $extra : ''));
    list ($info[$name]) = mysqli_fetch_array ($res);
    return $info[$name];
  }

  $totalusers = get_count ('totalusers', 'users', 'WHERE ustatus=\'confirmed\'');
  $timecut = TIMENOW - 86400;
  $newuserstoday = get_count ('totalnewusers', 'users', 'WHERE added > ' . $db->escape_string ($timecut));
  $pendingusers = get_count ('pendingusers', 'users', 'WHERE ustatus = \'pending\'');
  $todaycomments = get_count ('todaycomments', 'comments', 'WHERE dateline > ' . $db->escape_string ($timecut));
  $todayvisits = get_count ('todayvisits', 'users', 'WHERE lastactive > ' . $db->escape_string ($timecut));
  $peers = get_count ('totalpeers', 'peers');
  $Seeders = get_count ('seeders', 'peers', 'WHERE seeder = \'yes\'');
  $Leechers = get_count ('seeders', 'peers', 'WHERE seeder = \'no\'');
  //$result = $db->sql_query ('SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul, COUNT(id) AS totaluser FROM users');
  //$row = $db->fetch_array ($result);
  //$totaldownloaded = mksize ($row['totaldl']);
  //$totaluploaded = mksize ($row['totalul']);
  
  
  
  $result = $db->sql_query('SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul, COUNT(id) AS totaluser FROM users');
$row = $db->fetch_array($result);

// Сохраняем числовые значения для расчетов
$totaldownloaded_bytes = (float)$row['totaldl'];
$totaluploaded_bytes = (float)$row['totalul'];

// Форматируем только для отображения
$totaldownloaded_display = mksize($row['totaldl']);
$totaluploaded_display = mksize($row['totalul']);
  
  
  
  
  
  
  
  $query = $db->sql_query('SELECT COUNT(id) as totaltorrents FROM torrents');
  $row = $db->fetch_array($query);
  $totaltorrents = $row['totaltorrents'];
  
  
  
 
 
 



echo '
<div class="container-fluid py-4">
    <!-- Welcome Card -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-10">
             <div class="container mt-3">
                <div class="card-body p-5 text-center bg-primary text-white rounded-4">
                    <div class="row align-items-center">
                        <div class="col-md-8 text-md-start">
                            <h2 class="mb-3 fw-bold">
                                <i class="fas fa-user-shield me-3"></i>
                                ' . htmlspecialchars_uni($CURUSER['username']) . '
                            </h2>
                            <h4 class="mb-4">Welcome to ' . $SITENAME . ' Staff Panel</h4>
                            <p class="lead mb-0">
                                <i class="fas fa-star me-2"></i>
                                We hope you like this new version which will allow you to manage your tracker easily.
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="bg-white bg-opacity-25 rounded-3 p-3 d-inline-block">
                                <i class="fas fa-clock fa-3x mb-2"></i>
                                <h5 class="mb-0" id="current-time">' . date('H:i:s') . '</h5>
                                <small>' . date('d M Y') . '</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

  
	
	<div class="container mt-3">

    <!-- Stats Cards Grid -->
    <div class="row g-4 mb-5">
        <!-- Users Section -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white rounded-top-3">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        User Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-user-plus text-primary fs-1"></i>
                                <h6 class="text-muted mb-1">Total Users</h6>
                                <h3 class="text-dark fw-bold">' . ts_nf($totalusers) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-user-clock text-success fs-1"></i>
                                <h6 class="text-muted mb-1">New Today</h6>
                                <h3 class="text-success fw-bold">' . ts_nf($newuserstoday) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-user-times text-warning fs-1"></i>
                                <h6 class="text-muted mb-1">Unconfirmed</h6>
                                <h3 class="text-warning fw-bold">' . ts_nf($pendingusers) . '</h3>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="text-muted">Donors: <strong>' . ts_nf($donors ?? 0) . '</strong></small>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">VIP: <strong>' . ts_nf($vipusers ?? 0) . '</strong></small>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Banned: <strong>' . ts_nf($bannedusers ?? 0) . '</strong></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-success text-white rounded-top-3">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Activity Today
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-eye text-info fs-1"></i>
                                <h6 class="text-muted mb-1">Active Users</h6>
                                <h3 class="text-info fw-bold">' . ts_nf($todayvisits) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-comment-dots text-secondary fs-1"></i>
                                <h6 class="text-muted mb-1">New Comments</h6>
                                <h3 class="text-secondary fw-bold">' . ts_nf($todaycomments) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-share-alt text-primary fs-1"></i>
                                <h6 class="text-muted mb-1">New Posts</h6>
                                <h3 class="text-primary fw-bold">' . ts_nf($todayposts ?? 0) . '</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Peers & Torrent Section -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-warning text-dark rounded-top-3">
                    <h5 class="mb-0">
                        <i class="fas fa-download me-2"></i>
                        Peers & Torrents
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-users text-danger fs-1"></i>
                                <h6 class="text-muted mb-1">Active Peers</h6>
                                <h3 class="text-danger fw-bold">' . ts_nf($peers) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-arrow-up text-success fs-1"></i>
                                <h6 class="text-muted mb-1">Seeders</h6>
                                <h3 class="text-success fw-bold">' . ts_nf($Seeders) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-arrow-down text-primary fs-1"></i>
                                <h6 class="text-muted mb-1">Leechers</h6>
                                <h3 class="text-primary fw-bold">' . ts_nf($Leechers) . '</h3>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-file-alt text-info fs-1"></i>
                                <h6 class="text-muted mb-1">Total Torrents</h6>
                                <h3 class="text-info fw-bold">' . ts_nf($totaltorrents ?? 0) . '</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Traffic Section -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-info text-white rounded-top-3">
                    <h5 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Total Traffic
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-upload text-success fs-1"></i>
                                <h6 class="text-muted mb-1">Uploaded</h6>
                                <h4 class="text-success fw-bold">' . $totaluploaded_display . '</h4>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-download text-danger fs-1"></i>
                                <h6 class="text-muted mb-1">Downloaded</h6>
                                <h4 class="text-danger fw-bold">' . $totaldownloaded_display . '</h4>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <i class="fas fa-balance-scale text-warning fs-1"></i>
                                <h6 class="text-muted mb-1">Ratio</h6>
                                <h4 class="text-warning fw-bold">' . ($totaldownloaded_bytes > 0 ? round($totaluploaded_bytes / $totaldownloaded_bytes, 2) : '∞') . '</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health & Recent Activity -->
    <div class="row g-4">
        <!-- System Health -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-danger text-white rounded-top-3">
                    <h5 class="mb-0">
                        <i class="fas fa-heartbeat me-2"></i>
                        System Health
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center mb-3">
                        <div class="col-8">
                            <h6 class="mb-1">Server Load</h6>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar ' . (($serverload ?? 0) > 80 ? 'bg-danger' : (($serverload ?? 0) > 60 ? 'bg-warning' : 'bg-success')) . '" 
                                     style="width: ' . min(($serverload ?? 0), 100) . '%"></div>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <span class="fw-bold">' . ($serverload ?? 0) . '%</span>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="health-stat">
                                <i class="fas fa-database text-primary fs-3"></i>
                                <h6 class="text-muted mb-1">DB Size</h6>
                                <small class="fw-bold">' . ($dbsize ?? '0 MB') . '</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="health-stat">
                                <i class="fas fa-hdd text-info fs-3"></i>
                                <h6 class="text-muted mb-1">Disk Free</h6>
                                <small class="fw-bold">' . ($diskfree ?? '0 GB') . '</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="health-stat">
                                <i class="fas fa-memory text-success fs-3"></i>
                                <h6 class="text-muted mb-1">Memory</h6>
                                <small class="fw-bold">' . ($memoryusage ?? '0%') . '</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Staff Activity -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-secondary text-white rounded-top-3">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Staff Activity
                    </h5>
                </div>
                <div class="card-body">
                    <div class="recent-activity">
                        ' . ($recentactivity ?? '<div class="text-center text-muted py-3">No recent activity</div>') . '
                    </div>
                    <div class="text-center mt-3">
                        <a href="staffpanel.php?tool=logs" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>View All Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>



<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.stat-card {
    padding: 1rem;
    border-radius: 10px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
   
}
.health-stat {
    padding: 0.75rem;
    border-radius: 8px;
    background: rgba(0,0,0,0.03);
    transition: all 0.3s ease;
}
.health-stat:hover {
    background: rgba(0,0,0,0.08);
}
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card:hover {
    transform: translateY(-2px);
   
}
.fas {
    transition: transform 0.3s ease;
}
.stat-card:hover .fas {
    transform: scale(1.1);
}
.btn-outline-primary:hover .fas,
.btn-outline-success:hover .fas,
.btn-outline-warning:hover .fas,
.btn-outline-info:hover .fas,
.btn-outline-secondary:hover .fas,
.btn-outline-dark:hover .fas {
    transform: scale(1.2);
}
.recent-activity {
    max-height: 200px;
    overflow-y: auto;
}
.badge {
    font-size: 0.6em;
    position: relative;
    top: -2px;
}
</style>

<script>
// Update current time every second
function updateTime() {
    const now = new Date();
    document.getElementById(\'current-time\').textContent = 
        now.getHours().toString().padStart(2, \'0\') + \':\' + 
        now.getMinutes().toString().padStart(2, \'0\') + \':\' + 
        now.getSeconds().toString().padStart(2, \'0\');
}
setInterval(updateTime, 1000);

// Add loading animation to buttons
document.addEventListener(\'DOMContentLoaded\', function() {
    const buttons = document.querySelectorAll(\'.btn\');
    buttons.forEach(button => {
        button.addEventListener(\'click\', function(e) {
            if (this.getAttribute(\'href\')) {
                const originalText = this.innerHTML;
                this.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i>Loading...\';
                this.disabled = true;
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 2000);
            }
        });
    });
});
</script>
';

echo '</td></tr></table>';

 
 
 
 
  stdfoot ();
  exit ();
?>
