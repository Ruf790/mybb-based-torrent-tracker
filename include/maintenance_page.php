<?php
function render_maintenance_page()
{
    
	global $SITENAME, $BASEURL, $offline_minutes;
	
	
	
	$randomId = strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $siteName = defined('SITENAME') ? SITENAME : 'Our Site';
    $siteVersion = defined('SITE_VERSION') ? SITE_VERSION : 'v1.0';
    $startedAt = date('F j, Y \a\t g:i A');
    $MAINTENANCE_STARTED = $startedAt;

    // Определяем оставшееся время
    $remainingMinutes = 0;
    $isUnlimited = false;
    
    if ($offline_minutes === 'unlimited') 
	{
        $isUnlimited = true;
        $remainingText = "until manually restored";
    } 
	elseif (!empty($offline_minutes) && is_numeric($offline_minutes)) 
	{
        $remainingMinutes = max(0, ceil(($offline_minutes - time()) / 60));
        $remainingText = $remainingMinutes > 0 ? "about $remainingMinutes minutes" : "any moment now";
    } 
	else 
	{
        $remainingText = "soon";
    }
	
	
	
	
	

    ob_start();
    ?>


<?php

$MAINTENANCE_STARTED = date('F j, Y \a\t g:i A');



?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Maintenance | <?php echo $SITENAME; ?></title>
  <link href="<?php echo $BASEURL; ?>/include/templates/default/style/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $BASEURL; ?>/include/templates/default/style/bootstrap-icons.css">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
      --primary-light: rgba(13, 110, 253, 0.1);
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
      background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%230d6efd' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
    }
    
    .maintenance-card {
      max-width: 700px;
      width: 100%;
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 15px 40px rgba(13, 110, 253, 0.15);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
      border: 1px solid rgba(13, 110, 253, 0.2);
      animation: float 6s ease-in-out infinite;
    }
    
    .maintenance-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 50px rgba(13, 110, 253, 0.25);
    }
    
    .card-header {
      background: var(--primary-gradient);
      color: white;
      padding: 25px;
      display: flex;
      align-items: center;
      gap: 20px;
      position: relative;
      overflow: hidden;
    }
    
    .card-header::after {
      content: "";
      position: absolute;
      top: -50%;
      right: -50%;
      width: 100%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
      transform: rotate(30deg);
    }
    
    .card-body {
      padding: 35px;
      background: white;
    }
    
    .maintenance-icon {
      font-size: 3rem;
      filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
      z-index: 1;
      animation: pulse 2s infinite;
    }
    
    .btn-maintenance {
      border: 2px solid #0d6efd;
      color: #0d6efd;
      font-weight: 600;
      padding: 10px 25px;
      border-radius: 8px;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    
    .btn-maintenance:hover {
      background: var(--primary-gradient);
      color: white;
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3);
    }
    
    .progress-container {
      margin: 25px 0;
      position: relative;
    }
    
    .progress-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 0.9rem;
      color: #495057;
    }
    
    .progress {
      height: 10px;
      border-radius: 5px;
      background-color: #e9ecef;
      overflow: hidden;
    }
    
    .progress-bar {
      background: var(--primary-gradient);
      border-radius: 5px;
      width: 75%;
      animation: progress-animation 3s infinite ease-in-out;
      position: relative;
      overflow: hidden;
    }
    
    .progress-bar::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
      animation: shine 2s infinite;
    }
    
    .countdown {
      font-size: 1.3rem;
      font-weight: 700;
      color: #0d6efd;
      background: var(--primary-light);
      padding: 10px 15px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      margin: 15px 0;
    }
    
    .task-list {
      list-style: none;
      padding: 0;
      margin: 25px 0;
    }
    
    .task-list li {
      padding: 12px 15px;
      margin-bottom: 10px;
      background: var(--primary-light);
      border-radius: 8px;
      display: flex;
      align-items: center;
      transition: transform 0.2s ease;
    }
    
    .task-list li:hover {
      transform: translateX(5px);
    }
    
    .task-list li i {
      margin-right: 12px;
      font-size: 1.2rem;
      color: #0d6efd;
    }
    
    .status-badge {
      position: absolute;
      top: 20px;
      right: 20px;
      background: rgba(0,0,0,0.2);
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    
    .maintenance-footer {
      margin-top: 25px;
      padding-top: 20px;
      border-top: 1px solid #dee2e6;
      font-size: 0.85rem;
      color: #6c757d;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-15px); }
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    
    @keyframes progress-animation {
      0% { width: 70%; }
      50% { width: 85%; }
      100% { width: 70%; }
    }
    
    @keyframes shine {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }
    
    /* Responsive adjustments */
    @media (max-width: 576px) {
      .card-header {
        flex-direction: column;
        text-align: center;
        gap: 10px;
      }
      
      .maintenance-icon {
        font-size: 2.5rem;
      }
      
      .card-body {
        padding: 25px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="card maintenance-card">
    <div class="card-header">
      <i class="bi bi-tools maintenance-icon"></i>
      <div>
        <h1 class="h2 mb-1">System Maintenance</h1>
        <p class="mb-0 opacity-90"><?php echo $SITENAME; ?> is currently undergoing scheduled maintenance</p>
      </div>
      
	   <span class="status-badge"><?= $isUnlimited ? 'ONGOING' : ($remainingMinutes > 0 ? 'ONGOING' : 'FINALIZING') ?></span>
	  
	  
    </div>
    <div class="card-body">
      <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 d-flex align-items-center">
        <i class="bi bi-info-circle-fill me-3 fs-4"></i>
        <div>
          <strong>We\'re improving your experience!</strong> Our team is performing important updates to serve you better.
        </div>
      </div>
      
      <p class="mb-4">We apologize for the inconvenience. The website will be back online as soon as possible with exciting improvements.</p>
      
      <div class="progress-container">
        <div class="progress-label">
          <span>Maintenance Progress</span>
          <span id="progress-percent">75%</span>
        </div>
        <div class="progress">
          <div class="progress-bar progress-bar-striped"></div>
        </div>
      </div>
      
      <div class="text-center my-4">
        <div class="countdown">
          <i class="bi bi-clock-history me-2"></i>
           Estimated completion: <span id="countdown"><?= $isUnlimited ? 'until manually restored' : $remainingText ?></span>
		  
        </div>
		
		<?php if ($isUnlimited): ?>
          <div class="alert alert-info mt-2">
            <i class="bi bi-infinity"></i> Maintenance mode is set to unlimited duration
          </div>
        <?php endif; ?>
		
		
		
      </div>
      
      <h3 class="h5 mb-3">Current Tasks:</h3>
      <ul class="task-list">
        <li>
          <i class="bi bi-server"></i>
          <span>Server infrastructure upgrades</span>
        </li>
        <li>
          <i class="bi bi-database-check"></i>
          <span>Database optimization</span>
        </li>
        <li>
          <i class="bi bi-shield-lock"></i>
          <span>Security enhancements</span>
        </li>
        <li>
          <i class="bi bi-lightning-charge"></i>
          <span>Performance improvements</span>
        </li>
      </ul>
      
      <div class="d-flex justify-content-center mt-4">
        <button onclick="location.reload()" class="btn btn-maintenance">
          <i class="bi bi-arrow-repeat me-2"></i> Refresh Page
        </button>
      </div>
      
      <div class="maintenance-footer">
        <div>
          <i class="bi bi-calendar-event me-1"></i>
          Maintenance started: <strong><?= $MAINTENANCE_STARTED ?></strong>
        </div>
        <div>
          <i class="bi bi-patch-check me-1"></i>
          Version: <?php echo $SITENAME; ?>
        </div>
      </div>
    </div>
  </div>

 
  
  
  
<?
$remainingMinutes = 0;
if (!empty($offline_minutes) && is_numeric($offline_minutes)) 
{
    $remainingMinutes = max(0, ceil(($offline_minutes - time()) / 60));
}

?>
  
  
  
  
    <script>
    // Обновленный скрипт с учетом unlimited режима
    <?php if (!$isUnlimited && $remainingMinutes > 0): ?>
    let minutes = <?= $remainingMinutes ?>;
    const countdownElement = document.getElementById("countdown");
    const progressPercent = document.getElementById("progress-percent");
    const statusBadge = document.querySelector(".status-badge");
    
    const updateCountdown = () => {
      if (minutes > 0) {
        minutes = Math.max(0, minutes - 1);
        
        // Обновление прогресса
        const progress = 75 + Math.floor((25 * (<?= $remainingMinutes ?> - minutes)) / <?= $remainingMinutes ?>);
        progressPercent.textContent = progress + "%";
        document.querySelector(".progress-bar").style.width = progress + "%";
        
        // Обновление текста
        if (minutes > 45) {
          countdownElement.textContent = "about " + Math.ceil(minutes/60) + " hour" + (Math.ceil(minutes/60) > 1 ? "s" : "");
        } else if (minutes > 1) {
          countdownElement.textContent = "about " + minutes + " minutes";
        } else if (minutes === 1) {
          countdownElement.textContent = "less than a minute";
        } else {
          countdownElement.textContent = "any moment now";
          statusBadge.textContent = "FINALIZING";
          statusBadge.style.background = "var(--primary-gradient)";
        }
      }
    };
    
    updateCountdown();
    setInterval(updateCountdown, 60000);
    <?php endif; ?>
    
    // Анимации (работают в любом режиме)
    const card = document.querySelector(".maintenance-card");
    let startTime = null;
    
    const floatAnimation = (timestamp) => {
      if (!startTime) startTime = timestamp;
      const progress = (timestamp - startTime) / 6000;
      const floatDistance = Math.sin(progress * Math.PI * 2) * 15;
      card.style.transform = `translateY(${floatDistance}px)`;
      requestAnimationFrame(floatAnimation);
    };
    
    requestAnimationFrame(floatAnimation);
    
    const progressBar = document.querySelector(".progress-bar");
    setInterval(() => {
      progressBar.style.animation = "none";
      void progressBar.offsetWidth;
      progressBar.style.animation = "progress-animation 3s infinite ease-in-out, shine 2s infinite";
    }, 3000);
  </script>
  
  
</body>
</html>







    <?php
    echo ob_get_clean();
}


