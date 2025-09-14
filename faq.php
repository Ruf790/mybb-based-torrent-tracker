<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  require_once 'global.php';
  gzip ();
  
  define ('TSFAQ_VERSION', '1.2.3');
  
  stdhead ('sfdsff');


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мини иконки статусов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --guest: #94a3b8;
            --user: #4ade80;
            --power-user: #60a5fa;
            --vip: #fbbf24;
            --uploader: #38bdf8;
            --moderator: #2dd4bf;
            --admin: #818cf8;
            --sysop: #a78bfa;
            --banned: #f87171;
        }
        
        
        
        .compact-icons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            max-width: 600px;
        }
        
        .icon-compact {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
        }
        
        .icon-compact:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .icon-compact i {
            font-size: 1.5rem;
        }
        
        .icon-compact::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--color);
            border-radius: 0 0 12px 12px;
        }
        
        /* Мини-анимации */
        @keyframes mini-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        
        @keyframes mini-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @keyframes mini-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes mini-glow {
            0% { filter: drop-shadow(0 0 2px var(--color)); }
            50% { filter: drop-shadow(0 0 4px var(--color)); }
            100% { filter: drop-shadow(0 0 2px var(--color)); }
        }
        
        /* Стили статусов */
        .guest { --color: var(--guest); }
        .guest i { animation: mini-pulse 3s infinite; }
        
        .user { --color: var(--user); }
        .user i { animation: mini-float 4s infinite; }
        
        .power-user { --color: var(--power-user); }
        .power-user i { animation: mini-spin 6s linear infinite; }
        
        .vip { --color: var(--vip); }
        .vip i { animation: mini-pulse 1.5s infinite; }
        
        .uploader { --color: var(--uploader); }
        .uploader i { animation: mini-float 3s infinite; }
        
        .moderator { --color: var(--moderator); }
        .moderator i { animation: mini-pulse 2s infinite; }
        
        .admin { --color: var(--admin); }
        .admin i { animation: mini-glow 2s infinite; }
        
        .sysop { --color: var(--sysop); }
        .sysop i { animation: mini-spin 8s linear infinite; }
        
        .banned { --color: var(--banned); }
        .banned i { animation: mini-pulse 0.8s infinite; }
        
        /* Подсказки */
        .icon-compact {
            position: relative;
        }
        
        .icon-compact::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #334155;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            margin-bottom: 8px;
        }
        
        .icon-compact:hover::before {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="compact-icons">
        <!-- Гость -->
        <div class="icon-compact guest" data-tooltip="Гость (UC_GUEST)">
            <i class="bi bi-person-fill" style="color: var(--guest)"></i>
        </div>
        
        <!-- Пользователь -->
        <div class="icon-compact user" data-tooltip="Пользователь (UC_USER)">
            <i class="bi bi-person-check-fill" style="color: var(--user)"></i>
        </div>
        
        <!-- Power User -->
        <div class="icon-compact power-user" data-tooltip="Power User (UC_POWER_USER)">
            <i class="bi bi-gear-fill" style="color: var(--power-user)"></i>
        </div>
        
        <!-- VIP -->
        <div class="icon-compact vip" data-tooltip="VIP (UC_VIP)">
            <i class="bi bi-star-fill" style="color: var(--vip)"></i>
        </div>
        
        <!-- Uploader -->
        <div class="icon-compact uploader" data-tooltip="Uploader (UC_UPLOADER)">
            <i class="bi bi-cloud-arrow-up-fill" style="color: var(--uploader)"></i>
        </div>
        
        <!-- Модератор -->
        <div class="icon-compact moderator" data-tooltip="Модератор (UC_MODERATOR)">
            <i class="bi bi-shield-check" style="color: var(--moderator)"></i>
        </div>
        
        <!-- Администратор -->
        <div class="icon-compact admin" data-tooltip="Администратор (UC_ADMINISTRATOR)">
            <i class="bi bi-shield-lock" style="color: var(--admin)"></i>
        </div>
        
        <!-- Системный оператор -->
        <div class="icon-compact sysop" data-tooltip="SysOp (UC_SYSOP)">
            <i class="bi bi-terminal-fill" style="color: var(--sysop)"></i>
        </div>
        
        <!-- Заблокированный -->
        <div class="icon-compact banned" data-tooltip="Заблокирован (UC_BANNED)">
            <i class="bi bi-person-slash" style="color: var(--banned)"></i>
        </div>
    </div>
</body>
</html>

<?


  stdfoot ();
?>
