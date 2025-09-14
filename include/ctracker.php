<?php
// TSSEv56 - Enhanced Security Filter
define('CT_VERSION', 'v0.7-enhanced');

if (!defined('IN_SCRIPT_TSSEv56')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

function is_malicious_request($input, $patterns) {
    foreach ($patterns as $pattern) {
        if (stripos($input, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

// Запрещённые шаблоны (XSS, SQLi, LFI, RFI)
$attack_patterns = [
    // XSS
    '<script', 'javascript:', 'onerror=', 'onload=', 'alert(', 'prompt(', 'eval(', 'document.cookie', 'img src',
    // SQLi
    "' or 1=1", '" or 1=1', 'union select', 'select * from', 'drop table', '--', ';--', 'insert into', 'update ', 'delete from', 'xp_',
    // LFI/RFI
    '../../', '/etc/passwd', 'php://', 'file://', 'data://', 'input://', 'expect://', 'wget ', 'curl ', 'include(', 'require(', 'base64_decode',
    // Command injection / Shell
    '`', '||', '|', ';', '$(', '&&', 'chmod', 'chown', 'rm -rf', 'cp ', 'mv ', 'wget ', 'perl ', 'python ', '/bin/sh', '/bin/bash',
    // PHP internal
    '<?php', '?>', '$_GET', '$_POST', '$_REQUEST', '$_SERVER'
];

// Проверяем QUERY_STRING
$query_string = urldecode($_SERVER['QUERY_STRING'] ?? '');
$uri = $_SERVER['REQUEST_URI'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (is_malicious_request($query_string, $attack_patterns) || is_malicious_request($uri, $attack_patterns)) {
    $log = sprintf(
        '(Possible Hacking Attempt Detected) IP: %s -- Agent: %s -- URL: %s',
        htmlspecialchars($ip),
        htmlspecialchars($user_agent),
        htmlspecialchars($uri)
    );

    // Логируем
    if (function_exists('write_log')) {
        write_log($log);
    } else {
        // Бекап: Пишем напрямую
        $entry = date("Y-m-d H:i:s") . " - " . $log . "\n";
        file_put_contents(__DIR__ . '/logs/hack_attempts.log', $entry, FILE_APPEND);
    }

    // Показываем юзеру
    

$randomId = 'SEC-' . rand(100000, 999999);

exit('
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Warning</title>
  <link href="'.$BASEURL.'/include/templates/default/style/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">
  
  
  <style>
    :root {
      --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      background-image: radial-gradient(circle at 10% 20%, rgba(248, 249, 250, 0.9) 0%, rgba(248, 249, 250, 0.8) 90%);
    }
    
    .security-card {
      max-width: 650px;
      width: 100%;
      border: none;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(220, 53, 69, 0.2);
      transition: transform 0.3s ease;
    }
    
    .security-card:hover {
      transform: translateY(-5px);
    }
    
    .card-header {
      background: var(--danger-gradient);
      color: white;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .card-body {
      padding: 30px;
    }
    
    .security-icon {
      font-size: 2.5rem;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }
    
    .btn-danger-outline {
      border: 2px solid #dc3545;
      color: #dc3545;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-danger-outline:hover {
      background: var(--danger-gradient);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }
    
    .contact-link {
      text-decoration: none;
      font-weight: 500;
      color: #dc3545;
      border-bottom: 1px dashed #dc3545;
      transition: all 0.2s ease;
    }
    
    .contact-link:hover {
      color: #a71d2a;
      border-bottom: 1px solid #a71d2a;
    }
    
    .pulse {
      animation: pulse 2s infinite;
    }
	
	
	
	.security-icon {
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.security-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
}
.security-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 40px rgba(220, 53, 69, 0.3);
}
	
	
	
	
	
	
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
  </style>
</head>
<body>
  <div class="card security-card pulse">
    <div class="card-header">
      <i class="bi bi-shield-lock security-icon"></i>
      <div>
        <h2 class="mb-0">Security Alert</h2>
        <p class="mb-0 opacity-75">Protection System Activated</p>
      </div>
    </div>
    <div class="card-body">
      <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Malicious activity detected!</strong> Our security systems have blocked this request.
      </div>
      
      <p class="mb-4">For your safety, we ve prevented this action from completing. This could be due to:</p>
      
      <ul class="mb-4">
        <li>Suspicious input patterns</li>
        <li>Potential security violation attempt</li>
        <li>Unusual request behavior</li>
      </ul>
      
      <div class="d-flex flex-column flex-sm-row gap-3">
        <a href="javascript:history.back()" class="btn btn-danger-outline btn-lg flex-grow-1">
          <i class="bi bi-arrow-left-circle me-2"></i> Return to Safety
        </a>
        <a href="/contact.php" class="btn btn-danger btn-lg flex-grow-1">
          <i class="bi bi-headset me-2"></i> Contact Support
        </a>
      </div>
      
      <div class="mt-4 text-center text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Incident ID: <strong>SEC-'.$randomId.'</strong> • 
		 <span id="timestamp"></span>
      </div>
	  
	  
	  
    </div>
  </div>

  <script src="'.$BASEURL.'/scripts/bootstrap.bundle.min.js"></script>
  
  
  <script>
    // Динамическая дата и время
    document.getElementById("timestamp").textContent = new Date().toLocaleString();
    
   
  </script>
  
  
</body>
</html>
');




}

