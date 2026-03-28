<?php

declare(strict_types=1);


function getagent(?string $httpagent = '', ?string $peer_id = ""): string
{
    global $lang;
    
    // Если оба параметра пустые, возвращаем "unknown"
    if (empty($httpagent) && empty($peer_id)) {
        return $lang->global['unknown'] ?? 'Unknown';
    }

    // Приоритет отдаём httpagent, если он есть
    $agent_string = !empty($httpagent) ? $httpagent : $peer_id;
    
    // База данных клиентов с улучшенным определением
    $clients = [
        // === ДЕСКТОПНЫЕ КЛИЕНТЫ ===
        // qBittorrent
        'qbittorrent' => [
            'patterns' => ['/qBittorrent/i', '/QB\d+\.\d+/', '/qB\/\d+\.\d+/'],
            'name' => 'qBittorrent',
            'icon' => '⚡',
            'category' => 'modern'
        ],
        // uTorrent
        'utorrent' => [
            'patterns' => ['/uTorrent/i', '/UT\d+\.\d+/', '/µTorrent/i', '/ÂµTorrent/i'],
            'name' => 'µTorrent',
            'icon' => '⛏️',
            'category' => 'classic'
        ],
        // Transmission
        'transmission' => [
            'patterns' => ['/Transmission/i', '/TR\d+\.\d+/', '/Transmission\/\d+/'],
            'name' => 'Transmission',
            'icon' => '🔄',
            'category' => 'lightweight'
        ],
        // Deluge
        'deluge' => [
            'patterns' => ['/Deluge/i', '/DE\d+\.\d+/', '/Deluge\/\d+/'],
            'name' => 'Deluge',
            'icon' => '🌊',
            'category' => 'modular'
        ],
        // Vuze
        'vuze' => [
            'patterns' => ['/Vuze/i', '/AZ\d+\.\d+/', '/Azureus/i'],
            'name' => 'Vuze',
            'icon' => '💎',
            'category' => 'feature-rich'
        ],
        // BitComet
        'bitcomet' => [
            'patterns' => ['/BitComet/i', '/BC\d+\.\d+/', '/BitComet\/\d+/'],
            'name' => 'BitComet',
            'icon' => '☄️',
            'category' => 'windows'
        ],
        // Tixati
        'tixati' => [
            'patterns' => ['/Tixati/i', '/TX\d+\.\d+/', '/Tixati\/\d+/'],
            'name' => 'Tixati',
            'icon' => '🌐',
            'category' => 'privacy'
        ],
        // FrostWire
        'frostwire' => [
            'patterns' => ['/FrostWire/i', '/FW\d+\.\d+/', '/FrostWire\/\d+/'],
            'name' => 'FrostWire',
            'icon' => '❄️',
            'category' => 'multimedia'
        ],
        // BiglyBT
        'biglybt' => [
            'patterns' => ['/BiglyBT/i', '/BL\d+\.\d+/', '/BiglyBT\/\d+/'],
            'name' => 'BiglyBT',
            'icon' => '🦋',
            'category' => 'opensource'
        ],
        // BitTorrent
        'bittorrent' => [
            'patterns' => ['/BitTorrent/i', '/BT\d+\.\d+/', '/BitTorrent\/\d+/', '/BitTorrent\d+/'],
            'name' => 'BitTorrent',
            'icon' => '🧲',
            'category' => 'original'
        ],
        // LibTorrent (rTorrent)
        'libtorrent' => [
            'patterns' => ['/libtorrent/i', '/RT\d+\.\d+/', '/rtorrent/i', '/rTorrent/i'],
            'name' => 'rTorrent',
            'icon' => '🐧',
            'category' => 'terminal'
        ],
        // Aria2
        'aria2' => [
            'patterns' => ['/aria2/i', '/A2\d+\.\d+/', '/aria2\/\d+/'],
            'name' => 'Aria2',
            'icon' => '🛠️',
            'category' => 'lightweight'
        ],
        // Halite
        'halite' => [
            'patterns' => ['/Halite/i'],
            'name' => 'Halite',
            'icon' => '🧂',
            'category' => 'web'
        ],
        // KTorrent
        'ktorrent' => [
            'patterns' => ['/KTorrent/i', '/ktorrent/i', '/KT\d+\.\d+/'],
            'name' => 'KTorrent',
            'icon' => '🐧',
            'category' => 'linux'
        ],
        // Tribler
        'tribler' => [
            'patterns' => ['/Tribler/i', '/tribler/i'],
            'name' => 'Tribler',
            'icon' => '🕸️',
            'category' => 'privacy'
        ],
        // Popcorn Time
        'popcorntime' => [
            'patterns' => ['/PopcornTime/i', '/Popcorn Time/i', '/Popcorn/i'],
            'name' => 'Popcorn Time',
            'icon' => '🍿',
            'category' => 'streaming'
        ],
        // Butter Project
        'butter' => [
            'patterns' => ['/Butter/i', '/butter/i'],
            'name' => 'Butter Project',
            'icon' => '🧈',
            'category' => 'streaming'
        ],
        // PicoTorrent
        'picotorrent' => [
            'patterns' => ['/PicoTorrent/i', '/PicoTorrent/i', '/Pico\/\d+/'],
            'name' => 'PicoTorrent',
            'icon' => '🔬',
            'category' => 'lightweight'
        ],
        // Shareaza
        'shareaza' => [
            'patterns' => ['/Shareaza/i', '/shareaza/i', '/Raza/i'],
            'name' => 'Shareaza',
            'icon' => '🦅',
            'category' => 'p2p'
        ],
        // BitSpirit
        'bitspirit' => [
            'patterns' => ['/BitSpirit/i', '/bitspirit/i', '/BS\d+\.\d+/'],
            'name' => 'BitSpirit',
            'icon' => '👻',
            'category' => 'windows'
        ],
        // BitLord
        'bitlord' => [
            'patterns' => ['/BitLord/i', '/bitlord/i', '/BLord/i'],
            'name' => 'BitLord',
            'icon' => '👑',
            'category' => 'windows'
        ],
        // Xunlei (Thunder)
        'xunlei' => [
            'patterns' => ['/Xunlei/i', '/xunlei/i', '/Thunder/i', '/XL\d+\.\d+/'],
            'name' => 'Xunlei',
            'icon' => '⚡',
            'category' => 'chinese'
        ],
        // Free Download Manager
        'fdm' => [
            'patterns' => ['/FDM/i', '/fdm/i', '/Free Download Manager/i'],
            'name' => 'Free Download Manager',
            'icon' => '📥',
            'category' => 'download'
        ],
        // MLDonkey
        'mldonkey' => [
            'patterns' => ['/MLDonkey/i', '/mldonkey/i', '/ML\/\d+/'],
            'name' => 'MLDonkey',
            'icon' => '🐴',
            'category' => 'network'
        ],
        // BitTornado
        'bittornado' => [
            'patterns' => ['/BitTornado/i', '/bittornado/i', '/T\d+\.\d+/'],
            'name' => 'BitTornado',
            'icon' => '🌪️',
            'category' => 'classic'
        ],
        // ABC (Yet Another BitTorrent Client)
        'abc' => [
            'patterns' => ['/ABC\/\d+/', '/ABC \d+/'],
            'name' => 'ABC',
            'icon' => '🔤',
            'category' => 'classic'
        ],
        // BitRocket
        'bitrocket' => [
            'patterns' => ['/BitRocket/i', '/bitrocket/i'],
            'name' => 'BitRocket',
            'icon' => '🚀',
            'category' => 'mac'
        ],
        // TomatoTorrent
        'tomatotorrent' => [
            'patterns' => ['/TomatoTorrent/i', '/tomato/i'],
            'name' => 'TomatoTorrent',
            'icon' => '🍅',
            'category' => 'mac'
        ],
        // XTorrent
        'xtorrent' => [
            'patterns' => ['/XTorrent/i', '/xtorrent/i'],
            'name' => 'XTorrent',
            'icon' => '🦋',
            'category' => 'mac'
        ],

        // === МОБИЛЬНЫЕ КЛИЕНТЫ ANDROID ===
        // Flud
        'flud' => [
            'patterns' => ['/Flud/i', '/FLUD/i', '/fludtorrent/i'],
            'name' => 'Flud',
            'icon' => '💧',
            'category' => 'android'
        ],
        // tTorrent
        'ttorrent' => [
            'patterns' => ['/tTorrent/i', '/TTorrent/i', '/ttorrent\/\d+/'],
            'name' => 'tTorrent',
            'icon' => '📱',
            'category' => 'android'
        ],
        // LibreTorrent
        'libretorrent' => [
            'patterns' => ['/LibreTorrent/i', '/libretorrent/i', '/Libre\/\d+/'],
            'name' => 'LibreTorrent',
            'icon' => '📚',
            'category' => 'android'
        ],
        // FrostWire for Android
        'frostwire_android' => [
            'patterns' => ['/FrostWire Android/i', '/FrostWireMobile/i', '/FWA\/\d+/'],
            'name' => 'FrostWire Android',
            'icon' => '❄️',
            'category' => 'android'
        ],
        // BiglyBT Android
        'biglybt_android' => [
            'patterns' => ['/BiglyBT Android/i', '/BiglyBTMobile/i', '/BAM\/\d+/'],
            'name' => 'BiglyBT Android',
            'icon' => '🦋',
            'category' => 'android'
        ],
        // aTorrent
        'atorrent' => [
            'patterns' => ['/aTorrent/i', '/ATorrent/i', '/aTorrent\/\d+/'],
            'name' => 'aTorrent',
            'icon' => '📲',
            'category' => 'android'
        ],
        // TorrDroid
        'torrdroid' => [
            'patterns' => ['/TorrDroid/i', '/TorrDroid/i', '/TorrDroid\/\d+/'],
            'name' => 'TorrDroid',
            'icon' => '🤖',
            'category' => 'android'
        ],
        // TorrSE
        'torrse' => [
            'patterns' => ['/TorrSE/i', '/TorrSE/i', '/TorrSE\/\d+/'],
            'name' => 'TorrSE',
            'icon' => '🔍',
            'category' => 'android'
        ],
        // TurboTorrent
        'turbotorrent' => [
            'patterns' => ['/TurboTorrent/i', '/Turbo Torrent/i', '/Turbo\/\d+/'],
            'name' => 'TurboTorrent',
            'icon' => '🌀',
            'category' => 'android'
        ],
        // Torrent Downloader
        'torrentdownloader' => [
            'patterns' => ['/Torrent Downloader/i', '/TorrentDownloader/i', '/TorrentDL/i'],
            'name' => 'Torrent Downloader',
            'icon' => '⬇️',
            'category' => 'android'
        ],
        // BitTorrent Mobile
        'bittorrent_mobile' => [
            'patterns' => ['/BitTorrent Mobile/i', '/BT Mobile/i', '/BTM\/\d+/'],
            'name' => 'BitTorrent Mobile',
            'icon' => '🧲',
            'category' => 'android'
        ],
        // µTorrent Android
        'utorrent_android' => [
            'patterns' => ['/µTorrent Android/i', '/uTorrent Android/i', '/UTA\/\d+/'],
            'name' => 'µTorrent Android',
            'icon' => '⛏️',
            'category' => 'android'
        ],
        // Torrent Client
        'torrentclient_android' => [
            'patterns' => ['/Torrent Client/i', '/TorrentClient/i', '/TCAndroid/i'],
            'name' => 'Torrent Client',
            'icon' => '📱',
            'category' => 'android'
        ],
        // Swarm Torrent
        'swarm' => [
            'patterns' => ['/Swarm Torrent/i', '/Swarm\/\d+/', '/SwarmTorrent/i'],
            'name' => 'Swarm',
            'icon' => '🐝',
            'category' => 'android'
        ],
        // P2P Torrent
        'p2ptorrent' => [
            'patterns' => ['/P2P Torrent/i', '/P2PTorrent/i', '/P2P\/\d+/'],
            'name' => 'P2P Torrent',
            'icon' => '🔗',
            'category' => 'android'
        ],
        // TorrMa
        'torrma' => [
            'patterns' => ['/TorrMa/i', '/torrma/i'],
            'name' => 'TorrMa',
            'icon' => '🔧',
            'category' => 'android'
        ],
        // Torrent Faster
        'torrentfaster' => [
            'patterns' => ['/Torrent Faster/i', '/TorrentFaster/i'],
            'name' => 'Torrent Faster',
            'icon' => '⚡',
            'category' => 'android'
        ],

        // === МОБИЛЬНЫЕ КЛИЕНТЫ iOS ===
        // iTorrent
        'itorrent' => [
            'patterns' => ['/iTorrent/i', '/iTorrent/i', '/iTorrent\/\d+/'],
            'name' => 'iTorrent',
            'icon' => '🍎',
            'category' => 'ios'
        ],
        // FoxTorrent
        'foxtorrent' => [
            'patterns' => ['/FoxTorrent/i', '/FoxTorrent/i', '/Fox\/\d+/'],
            'name' => 'FoxTorrent',
            'icon' => '🦊',
            'category' => 'ios'
        ],
        // Torrent Client iOS
        'torrentclient_ios' => [
            'patterns' => ['/Torrent Client/i', '/iOS.Torrent/i', '/TCiOS/i'],
            'name' => 'Torrent Client',
            'icon' => '📱',
            'category' => 'ios'
        ],
        // SwiftyTorrent
        'swiftytorrent' => [
            'patterns' => ['/SwiftyTorrent/i', '/Swifty/i', '/Swifty\/\d+/'],
            'name' => 'SwiftyTorrent',
            'icon' => '⚡',
            'category' => 'ios'
        ],
        // Downloads Lite
        'downloadslite' => [
            'patterns' => ['/Downloads Lite/i', '/DownloadsLite/i'],
            'name' => 'Downloads Lite',
            'icon' => '📥',
            'category' => 'ios'
        ],
        // TorrentBox
        'torrentbox' => [
            'patterns' => ['/TorrentBox/i', '/torrentbox/i'],
            'name' => 'TorrentBox',
            'icon' => '📦',
            'category' => 'ios'
        ],
        // iTransmission
        'itransmission' => [
            'patterns' => ['/iTransmission/i', '/itransmission/i'],
            'name' => 'iTransmission',
            'icon' => '🔄',
            'category' => 'ios'
        ],

        // === КРОССПЛАТФОРМЕННЫЕ МОБИЛЬНЫЕ ===
        // Transdroid
        'transdroid' => [
            'patterns' => ['/Transdroid/i', '/transdroid/i', '/Transdroid\/\d+/'],
            'name' => 'Transdroid',
            'icon' => '🤖',
            'category' => 'mobile'
        ],
        // TorrClient
        'torrclient' => [
            'patterns' => ['/TorrClient/i', '/TorrClient/i', '/TC\/\d+/'],
            'name' => 'TorrClient',
            'icon' => '📱',
            'category' => 'mobile'
        ],
        // Torrent Web
        'torrentweb' => [
            'patterns' => ['/TorrentWeb/i', '/Torrent Web/i', '/TWeb\/\d+/'],
            'name' => 'Torrent Web',
            'icon' => '🌐',
            'category' => 'mobile'
        ],
        // MobileTorrent
        'mobiletorrent' => [
            'patterns' => ['/MobileTorrent/i', '/Mobile Torrent/i', '/MT\/\d+/'],
            'name' => 'MobileTorrent',
            'icon' => '📲',
            'category' => 'mobile'
        ],

        // === СПЕЦИАЛЬНЫЕ И ГИБРИДНЫЕ КЛИЕНТЫ ===
        // WebTorrent
        'webtorrent' => [
            'patterns' => ['/WebTorrent/i', '/webtorrent/i', '/WebT\/\d+/'],
            'name' => 'WebTorrent',
            'icon' => '🌐',
            'category' => 'webbased'
        ],
        // WebTorrent Desktop
        'webtorrent_desktop' => [
            'patterns' => ['/WebTorrent Desktop/i', '/webtorrent-desktop/i'],
            'name' => 'WebTorrent Desktop',
            'icon' => '💻',
            'category' => 'webbased'
        ],
        // Peerflix
        'peerflix' => [
            'patterns' => ['/peerflix/i', '/Peerflix/i'],
            'name' => 'Peerflix',
            'icon' => '🎬',
            'category' => 'streaming'
        ],
        // Torrent Stream
        'torrentstream' => [
            'patterns' => ['/Torrent Stream/i', '/TorrentStream/i'],
            'name' => 'Torrent Stream',
            'icon' => '📺',
            'category' => 'streaming'
        ],
        // Ace Stream
        'acestream' => [
            'patterns' => ['/AceStream/i', '/acestream/i'],
            'name' => 'Ace Stream',
            'icon' => '🅰️',
            'category' => 'streaming'
        ],
        // Soda Player
        'sodaplayer' => [
            'patterns' => ['/Soda Player/i', '/SodaPlayer/i'],
            'name' => 'Soda Player',
            'icon' => '🥤',
            'category' => 'streaming'
        ],
        // Cheat Clients (для обнаружения)
        'ratio_master' => [
            'patterns' => ['/RatioMaster/i', '/ratiomaster/i', '/RM\d+/'],
            'name' => 'Ratio Master',
            'icon' => '⚠️',
            'category' => 'cheat'
        ],
        'mRator' => [
            'patterns' => ['/mRator/i', '/mrator/i'],
            'name' => 'mRator',
            'icon' => '⚠️',
            'category' => 'cheat'
        ],
        'shuMod' => [
            'patterns' => ['/shuMod/i', '/shumod/i'],
            'name' => 'shuMod',
            'icon' => '⚠️',
            'category' => 'cheat'
        ]
    ];

    // Определяем клиента
    $detected_client = null;
    $version = '';
    
    foreach ($clients as $client_id => $client_info) {
        foreach ($client_info['patterns'] as $pattern) {
            if (preg_match($pattern, $agent_string, $matches)) {
                $detected_client = $client_info;
                
                // Пытаемся извлечь версию
                if (preg_match('/(\d+\.\d+(?:\.\d+)?(?:\.\d+)?)/', $agent_string, $version_matches)) {
                    $version = $version_matches[1];
                }
                break 2;
            }
        }
    }

    // Форматируем результат
    if ($detected_client) {
        $client_name = $detected_client['name'];
        $icon = $detected_client['icon'];
        
        // Добавляем платформенный бейдж
        $platform_badge = '';
        $badge_class = '';
        
        switch ($detected_client['category']) {
            case 'android':
                $platform_badge = 'ANDROID';
                $badge_class = 'success';
                break;
            case 'ios':
                $platform_badge = 'iOS';
                $badge_class = 'primary';
                break;
            case 'mobile':
                $platform_badge = 'MOBILE';
                $badge_class = 'info';
                break;
            case 'webbased':
                $platform_badge = 'WEB';
                $badge_class = 'warning';
                break;
            case 'streaming':
                $platform_badge = 'STREAM';
                $badge_class = 'purple';
                break;
            case 'cheat':
                $platform_badge = 'CHEAT';
                $badge_class = 'danger';
                break;
            case 'chinese':
                $platform_badge = 'CN';
                $badge_class = 'secondary';
                break;
            case 'linux':
                $platform_badge = 'LINUX';
                $badge_class = 'dark';
                break;
            case 'mac':
                $platform_badge = 'MAC';
                $badge_class = 'secondary';
                break;
            case 'windows':
                $platform_badge = '🪟';
                $badge_class = '';
                break;
        }
        
        if ($platform_badge) {
            $platform_badge = ' <span class="badge bg-' . $badge_class . ' bg-opacity-20 text-' . $badge_class . ' border border-' . $badge_class . ' border-opacity-25" style="font-size: 0.6em;">' . $platform_badge . '</span>';
        }
        
        if ($version) {
            return "{$icon} {$client_name} {$version}{$platform_badge}";
        } else {
            return "{$icon} {$client_name}{$platform_badge}";
        }
    }

    // Если клиент не распознан, возвращаем оригинальную строку с базовой очисткой
    return clean_agent_string($agent_string);
}



function clean_agent_string(string $agent_string): string
{
    // Убираем лишние пробелы и спецсимволы
    $cleaned = trim($agent_string);
    $cleaned = preg_replace('/[^\x20-\x7E]/', '', $cleaned); // Убираем не-ASCII символы
    
    // Обрезаем слишком длинные строки
    if (strlen($cleaned) > 50) {
        $cleaned = substr($cleaned, 0, 47) . '...';
    }
    
    return $cleaned ?: 'Unknown';
}


/**
 * Дополнительная функция для получения расширенной информации о клиенте
 */
function get_agent_info(?string $httpagent = '', ?string $peer_id = ""): array
{
    $agent_string = getagent($httpagent, $peer_id);
    
    // Расширенная база категорий с описаниями и метриками
    $clients_categories = [
        'modern' => [
            'icon' => '🆕', 
            'description' => 'Modern desktop client', 
            'color' => 'blue',
            'keywords' => 'qBittorrent,Modern',
            'performance' => 'high',
            'privacy' => 'good',
            'features' => 'advanced'
        ],
        'classic' => [
            'icon' => '🏛️', 
            'description' => 'Classic desktop client', 
            'color' => 'gray',
            'keywords' => 'µTorrent,Classic',
            'performance' => 'medium',
            'privacy' => 'fair',
            'features' => 'standard'
        ],
        'lightweight' => [
            'icon' => '⚖️', 
            'description' => 'Lightweight client', 
            'color' => 'green',
            'keywords' => 'Transmission,PicoTorrent,Lightweight',
            'performance' => 'high',
            'privacy' => 'good',
            'features' => 'basic'
        ],
        'modular' => [
            'icon' => '🧩', 
            'description' => 'Modular client', 
            'color' => 'purple',
            'keywords' => 'Deluge,Modular',
            'performance' => 'medium',
            'privacy' => 'good',
            'features' => 'extensible'
        ],
        'feature-rich' => [
            'icon' => '🎛️', 
            'description' => 'Feature-rich client', 
            'color' => 'orange',
            'keywords' => 'Vuze,Feature-rich',
            'performance' => 'medium',
            'privacy' => 'fair',
            'features' => 'extensive'
        ],
        'windows' => [
            'icon' => '🪟', 
            'description' => 'Windows client', 
            'color' => 'blue',
            'keywords' => 'BitComet,BitSpirit,Windows',
            'performance' => 'medium',
            'privacy' => 'fair',
            'features' => 'windows-specific'
        ],
        'privacy' => [
            'icon' => '🕵️', 
            'description' => 'Privacy-focused client', 
            'color' => 'black',
            'keywords' => 'Tribler,Tixati,Privacy',
            'performance' => 'medium',
            'privacy' => 'excellent',
            'features' => 'privacy-focused'
        ],
        'multimedia' => [
            'icon' => '🎬', 
            'description' => 'Multimedia client', 
            'color' => 'pink',
            'keywords' => 'FrostWire,Multimedia',
            'performance' => 'medium',
            'privacy' => 'fair',
            'features' => 'media-focused'
        ],
        'opensource' => [
            'icon' => '📖', 
            'description' => 'Open source client', 
            'color' => 'green',
            'keywords' => 'qBittorrent,Deluge,Transmission,Open Source',
            'performance' => 'high',
            'privacy' => 'good',
            'features' => 'community-driven'
        ],
        'original' => [
            'icon' => '⚙️', 
            'description' => 'Original client', 
            'color' => 'blue',
            'keywords' => 'BitTorrent,Original',
            'performance' => 'low',
            'privacy' => 'fair',
            'features' => 'basic'
        ],
        'terminal' => [
            'icon' => '💻', 
            'description' => 'Terminal client', 
            'color' => 'dark',
            'keywords' => 'rTorrent,Aria2,Terminal',
            'performance' => 'high',
            'privacy' => 'good',
            'features' => 'cli-only'
        ],
        'web' => [
            'icon' => '🌐', 
            'description' => 'Web-based client', 
            'color' => 'yellow',
            'keywords' => 'WebTorrent,Halite,Web',
            'performance' => 'low',
            'privacy' => 'fair',
            'features' => 'browser-based'
        ],
        'android' => [
            'icon' => '🤖', 
            'description' => 'Android mobile client', 
            'color' => 'green',
            'keywords' => 'Flud,tTorrent,LibreTorrent,Android',
            'performance' => 'mobile',
            'privacy' => 'good',
            'features' => 'mobile-optimized'
        ],
        'ios' => [
            'icon' => '🍎', 
            'description' => 'iOS mobile client', 
            'color' => 'black',
            'keywords' => 'iTorrent,FoxTorrent,iOS',
            'performance' => 'mobile',
            'privacy' => 'good',
            'features' => 'mobile-optimized'
        ],
        'mobile' => [
            'icon' => '📱', 
            'description' => 'Cross-platform mobile', 
            'color' => 'blue',
            'keywords' => 'MobileTorrent,Transdroid,Mobile',
            'performance' => 'mobile',
            'privacy' => 'good',
            'features' => 'cross-platform'
        ],
        'linux' => [
            'icon' => '🐧', 
            'description' => 'Linux client', 
            'color' => 'orange',
            'keywords' => 'KTorrent,Linux',
            'performance' => 'high',
            'privacy' => 'good',
            'features' => 'linux-optimized'
        ],
        'streaming' => [
            'icon' => '🎬', 
            'description' => 'Streaming client', 
            'color' => 'purple',
            'keywords' => 'Popcorn Time,Butter,Streaming',
            'performance' => 'medium',
            'privacy' => 'poor',
            'features' => 'streaming-focused'
        ],
        'chinese' => [
            'icon' => '🇨🇳', 
            'description' => 'Chinese client', 
            'color' => 'red',
            'keywords' => 'Xunlei,Chinese',
            'performance' => 'medium',
            'privacy' => 'poor',
            'features' => 'region-specific'
        ],
        'cheat' => [
            'icon' => '⚠️', 
            'description' => 'Cheating client', 
            'color' => 'danger',
            'keywords' => 'Ratio Master,mRator,Cheat',
            'performance' => 'n/a',
            'privacy' => 'n/a',
            'features' => 'fake-stats'
        ],
        'unknown' => [
            'icon' => '❓', 
            'description' => 'Unknown client type', 
            'color' => 'secondary',
            'keywords' => '',
            'performance' => 'unknown',
            'privacy' => 'unknown',
            'features' => 'unknown'
        ]
    ];

    // Вспомогательные функции
    $checkKeywords = function(string $agent_string, string $keywords): bool {
        $keyword_list = explode(',', $keywords);
        foreach ($keyword_list as $keyword) {
            if (str_contains(strtolower($agent_string), strtolower(trim($keyword)))) {
                return true;
            }
        }
        return false;
    };

    $extractClientName = function(string $agent_string): string {
        // Убираем эмодзи и версию
        $clean_name = preg_replace('/[^\x20-\x7E]/', '', $agent_string);
        $clean_name = preg_replace('/\s+\d+\.\d+.*$/', '', $clean_name);
        return trim($clean_name) ?: 'Unknown';
    };

    $extractVersion = function(string $agent_string): string {
        if (preg_match('/(\d+\.\d+(?:\.\d+)?(?:\.\d+)?)/', $agent_string, $matches)) {
            return $matches[1];
        }
        return '';
    };

    $detectPlatform = function(string $agent_string, string $category): string {
        if (str_contains($agent_string, 'ANDROID') || $category === 'android') {
            return 'android';
        } elseif (str_contains($agent_string, 'iOS') || $category === 'ios') {
            return 'ios';
        } elseif (str_contains($agent_string, 'MOBILE') || $category === 'mobile') {
            return 'mobile';
        } elseif (str_contains($agent_string, 'WEB') || $category === 'web') {
            return 'web';
        } elseif (str_contains($agent_string, 'LINUX') || $category === 'linux') {
            return 'linux';
        } elseif (str_contains($agent_string, 'MAC') || str_contains($agent_string, '🍎')) {
            return 'mac';
        } elseif (str_contains($agent_string, 'WIN') || $category === 'windows') {
            return 'windows';
        }
        return 'desktop';
    };

    $calculateSecurityScore = function(string $category, string $client_name): array {
        $base_scores = [
            'modern' => 85, 'opensource' => 80, 'privacy' => 90, 'lightweight' => 75,
            'linux' => 70, 'terminal' => 80, 'android' => 65, 'ios' => 70,
            'mobile' => 60, 'classic' => 50, 'modular' => 70, 'feature-rich' => 45,
            'windows' => 40, 'multimedia' => 35, 'original' => 30, 'web' => 25,
            'streaming' => 20, 'chinese' => 15, 'cheat' => 0, 'unknown' => 10
        ];
        
        $score = $base_scores[$category] ?? 50;
        $issues = [];
        
        // Дополнительные проверки для конкретных клиентов
        if (str_contains($client_name, 'µTorrent')) {
            $score -= 10;
            $issues[] = 'Contains adware in some versions';
        }
        
        if (str_contains($client_name, 'Xunlei')) {
            $score -= 20;
            $issues[] = 'Known privacy concerns';
        }
        
        if (str_contains($client_name, 'BitComet')) {
            $score -= 5;
            $issues[] = 'Aggressive connection tactics';
        }
        
        $level = match(true) {
            $score >= 80 => 'high',
            $score >= 60 => 'good',
            $score >= 40 => 'fair',
            $score >= 20 => 'low',
            default => 'very low'
        };
        
        return ['score' => max(0, $score), 'level' => $level, 'issues' => $issues];
    };

    $checkCompatibility = function(string $client_name, string $version): array {
        $compatibility = [
            'level' => 'excellent',
            'notes' => [],
            'recommended' => true
        ];
        
        // Проверки совместимости
        if (empty($client_name) || $client_name === 'Unknown') {
            $compatibility['level'] = 'unknown';
            $compatibility['recommended'] = false;
        }
        
        if (str_contains($client_name, 'Ratio Master')) {
            $compatibility['level'] = 'blocked';
            $compatibility['notes'][] = 'Cheating client detected';
            $compatibility['recommended'] = false;
        }
        
        // Проверка версий
        if (str_contains($client_name, 'µTorrent') && version_compare($version, '2.2.1', '<')) {
            $compatibility['level'] = 'poor';
            $compatibility['notes'][] = 'Outdated version, security issues';
            $compatibility['recommended'] = false;
        }
        
        return $compatibility;
    };

    $getUsageStats = function(string $client_name): array {
        // Статистика популярности (условные данные)
        $popularity_data = [
            'qBittorrent' => ['popularity' => 'high', 'percentage' => 35],
            'µTorrent' => ['popularity' => 'high', 'percentage' => 25],
            'Transmission' => ['popularity' => 'medium', 'percentage' => 15],
            'Deluge' => ['popularity' => 'medium', 'percentage' => 8],
            'BitComet' => ['popularity' => 'medium', 'percentage' => 5],
            'default' => ['popularity' => 'low', 'percentage' => 1]
        ];
        
        $stats = $popularity_data[$client_name] ?? $popularity_data['default'];
        $stats['last_updated'] = date('Y-m-d');
        return $stats;
    };

    $getFeatureFlags = function(string $client_name, string $version): array {
        $flags = [];
        
        if (str_contains($client_name, 'qBittorrent')) {
            $flags = ['encryption', 'dht', 'pex', 'ip_filter', 'sequential_download'];
        } elseif (str_contains($client_name, 'µTorrent')) {
            $flags = ['encryption', 'dht', 'pex', 'utp'];
        } elseif (str_contains($client_name, 'Transmission')) {
            $flags = ['encryption', 'dht', 'pex', 'utp', 'watch_dir'];
        }
        
        return $flags;
    };

    $getConfigRecommendations = function(string $client_name): array {
        $recommendations = [];
        
        if (str_contains($client_name, 'qBittorrent')) {
            $recommendations = [
                'Enable encryption',
                'Use random port',
                'Disable DHT for private trackers'
            ];
        }
        
        return $recommendations;
    };

    $getKnownIssues = function(string $client_name, string $version): array {
        $issues = [];
        
        if (str_contains($client_name, 'µTorrent') && version_compare($version, '3.0', '>=')) {
            $issues[] = 'May contain bundled software';
        }
        
        return $issues;
    };

    // Определяем категорию по содержимому строки
    $category = 'unknown';
    $detected_client_name = '';
    
    foreach ($clients_categories as $cat_id => $cat_info) {
        if (str_contains($agent_string, $cat_info['icon']) || 
            (!empty($cat_info['keywords']) && $checkKeywords($agent_string, $cat_info['keywords']))) {
            $category = $cat_id;
            break;
        }
    }
    
    // Извлекаем название клиента из строки
    $detected_client_name = $extractClientName($agent_string);
    
    // Определяем платформу
    $platform = $detectPlatform($agent_string, $category);
    
    // Определяем версию
    $version = $extractVersion($agent_string);
    
    // Оценка безопасности клиента
    $security_score = $calculateSecurityScore($category, $detected_client_name);
    
    // Совместимость с трекером
    $compatibility = $checkCompatibility($detected_client_name, $version);
    
    // Статистика использования
    $usage_stats = $getUsageStats($detected_client_name);
    
    return [
        // Основная информация
        'display_name' => $agent_string,
        'client_name' => $detected_client_name,
        'version' => $version,
        'raw_agent' => $httpagent,
        'raw_peer_id' => $peer_id,
        
        // Классификация
        'category' => $category,
        'platform' => $platform,
        'category_icon' => $clients_categories[$category]['icon'] ?? '❓',
        'category_description' => $clients_categories[$category]['description'] ?? 'Unknown client type',
        'category_color' => $clients_categories[$category]['color'] ?? 'secondary',
        
        // Характеристики
        'performance' => $clients_categories[$category]['performance'] ?? 'unknown',
        'privacy_level' => $clients_categories[$category]['privacy'] ?? 'unknown',
        'feature_set' => $clients_categories[$category]['features'] ?? 'unknown',
        
        // Флаги
        'is_mobile' => in_array($platform, ['android', 'ios', 'mobile']),
        'is_desktop' => in_array($platform, ['desktop', 'linux', 'windows', 'mac']),
        'is_web' => $platform === 'web',
        'is_recognized' => !str_contains($agent_string, '❓') && $agent_string !== 'Unknown',
        'is_modern' => in_array($category, ['modern', 'opensource', 'lightweight']),
        'is_legacy' => in_array($category, ['classic', 'original']),
        
        // Безопасность
        'security_score' => $security_score['score'], // 0-100
        'security_level' => $security_score['level'], // low/medium/high
        'security_issues' => $security_score['issues'],
        'is_suspicious' => $category === 'cheat' || $security_score['score'] < 30,
        
        // Совместимость
        'compatibility' => $compatibility['level'], // excellent/good/fair/poor
        'compatibility_notes' => $compatibility['notes'],
        'recommended' => $compatibility['recommended'],
        
        // Статистика
        'usage_popularity' => $usage_stats['popularity'], // high/medium/low
        'usage_percentage' => $usage_stats['percentage'], // % среди всех клиентов
        'last_updated' => $usage_stats['last_updated'],
        
        // Дополнительная информация
        'feature_flags' => $getFeatureFlags($detected_client_name, $version),
        'config_recommendations' => $getConfigRecommendations($detected_client_name),
        'known_issues' => $getKnownIssues($detected_client_name, $version)
    ];
}




/**
 * Функция для получения HTML-представления информации о клиенте
 */
function get_agent_html(?string $httpagent = '', ?string $peer_id = "", bool $show_details = false): string
{
    $agent_info = get_agent_info($httpagent, $peer_id);
    
    if (!$show_details) {
        return '<span class="torrent-client" data-bs-toggle="tooltip" title="' 
               . htmlspecialchars($agent_info['category_description']) . '">'
               . $agent_info['display_name'] 
               . '</span>';
    }
    
    $platform_badge = '';
    if ($agent_info['is_mobile']) {
        $platform_icon = $agent_info['platform'] === 'android' ? '🤖' : 
                        ($agent_info['platform'] === 'ios' ? '🍎' : '📱');
        $platform_badge = '<span class="platform-badge">' . $platform_icon . '</span>';
    }
    
    return <<<HTML
<div class="torrent-client-detailed">
    <div class="client-main">
        <span class="client-icon">{$agent_info['category_icon']}</span>
        <span class="client-name">{$agent_info['display_name']}</span>
        {$platform_badge}
    </div>
    <div class="client-details">
        <small class="text-muted">{$agent_info['category_description']}</small>
    </div>
</div>
HTML;
}
	
	
	
	
	
	
	


function dltable($name, $arr, $torrent, $is_seeders = false)
{
    global $CURUSER, $pic_base_url, $lang, $usergroups, $is_mod, $BASEURL, $templates;

    $totalcount = is_array($arr) ? count($arr) : 0;
    
    // Минималистичный заголовок
    $p = '<div class="peers-header mb-4">
            <div class="d-flex align-items-center">
                <h4 class="fw-light mb-0 me-3">' . htmlspecialchars($name) . '</h4>
                <div class="d-flex gap-2">
                    <span class="badge bg-dark rounded-0 px-3 py-2">' . $totalcount . ' peers</span>
                    ' . ($is_seeders ? '<span class="badge bg-success rounded-0 px-3 py-2">SEEDING</span>' : '<span class="badge bg-danger rounded-0 px-3 py-2">LEECHING</span>') . '
                </div>
            </div>
            <hr class="my-3 opacity-25">
          </div>';

    if ($totalcount <= 0) {
        $p .= '<div class="text-center py-5 bg-light">
                 <i class="bi bi-cloud-slash fs-1 text-secondary opacity-25"></i>
                 <p class="text-muted mt-3">No active peers</p>
               </div>';
        return $p;
    }

    $now = TIMENOW;
    include_once(INC_PATH . '/functions_ratio.php');

    // Карточки вместо таблицы
    $s = '<div class="peers-grid">';
    
    foreach ($arr as $e) {
        // Проверка на невидимость (invisible)
        $is_invisible = (isset($e['invisible']) && $e['invisible'] == '1');
        
        // Обычный пользователь видит всех, кроме invisible
        if (!$is_mod && $CURUSER['id'] != $e['id']) {
            // Если пользователь invisible - показываем пустую карточку
            if ($is_invisible) {
                $progress = 100 * (1 - ($e["to_go"] / max(1, $torrent["size"])));
                $progress = min(100, max(0, $progress));
                
                $s .= '
                <div class="peer-card mb-3 p-3 bg-white border border-light">
                    <div class="d-flex align-items-start">
                        <!-- Бейдж вместо аватара -->
                        <div class="me-3">
                            <div class="anonymous-avatar-wrapper"><i class="bi bi-incognito fs-2 text-secondary"></i></div>
                        </div>
                        
                        <!-- Информация с пустыми данными -->
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="w-100">
                                    <div class="d-flex align-items-center">
                                        <span class="text-muted"><i class="bi bi-incognito me-2"></i>Invisible User</span>
                                    </div>
                                    
                                    <div class="d-flex gap-3 small text-secondary mt-2">
                                        <span class="text-muted"><i class="bi bi-dash-circle"></i> --</span>
                                        <span class="text-muted"><i class="bi bi-dash-circle"></i> --</span>
                                    </div>
                                </div>
                                
                                <!-- Invisible badge -->
                                <div class="text-end ms-3">
                                    <span class="badge bg-secondary rounded-0 px-3 py-2">
                                        <i class="bi bi-incognito me-1"></i>INVISIBLE
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Прогресс бар (виден всем) -->
                            <div class="mt-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Progress</span>
                                    <span class="fw-bold">' . sprintf("%.1f%%", $progress) . '</span>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar bg-' . ($is_seeders ? 'success' : 'info') . '" style="width: ' . $progress . '%"></div>
                                </div>
                            </div>
                            
                            <!-- Детали с пустыми данными -->
                            <div class="d-flex flex-wrap gap-4 mt-3 small">
                                <div>
                                    <span class="text-secondary d-block">Upload rate</span>
                                    <span class="fw-bold text-muted"><i class="bi bi-dash"></i> --</span>
                                </div>
                                <div>
                                    <span class="text-secondary d-block">Download rate</span>
                                    <span class="fw-bold text-muted"><i class="bi bi-dash"></i> --</span>
                                </div>
                                <div>
                                    <span class="text-secondary d-block">Connected</span>
                                    <span class="fw-bold">' . mkprettytime($now - $e["st"]) . '</span>
                                </div>
                                <div>
                                    <span class="text-secondary d-block">Idle</span>
                                    <span class="fw-bold">' . mkprettytime($now - $e["la"]) . '</span>
                                </div>
                                <div>
                                    <span class="text-secondary d-block">Client</span>
                                    <span class="fw-bold"><i class="bi bi-incognito"></i> Hidden</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
                continue;
            }
            
            // Если пользователь не invisible - показываем полные данные
            // Здесь будет код для отображения полных данных
        }

        $progress = 100 * (1 - ($e["to_go"] / max(1, $torrent["size"])));
        $progress = min(100, max(0, $progress));
        
        // Highlight для текущего пользователя
        $is_current = ($CURUSER["id"] == $e["id"]);
        $card_highlight = $is_current ? 'border-primary shadow current-user-card' : 'border-light';
        
        // IP display из старого кода
        $ip_display = $is_mod
            ? '<div class="mt-1">
			<small class="badge bg-dark bg-opacity-25 text-dark"><i class="bi bi-globe me-1"></i>' . htmlspecialchars_uni($e["ip"]) . ':<span class="fw-bold">' . (int)$e['port'] . '</span></small>
			
			<span><i class="bi bi-arrow-up text-success"></i> ' . mksize($e["uploaded"] ?? 0) . '</span>
                                <span><i class="bi bi-arrow-down text-info"></i> ' . mksize($e["downloaded"] ?? 0) . '</span>
			
			
			</div>
			  
			
			
			'
            : '<div class="mt-1">
			<small class="badge bg-light text-muted"><i class="bi bi-eye me-1"></i>' . preg_replace('/\.\d+\.\d+$/', '***', htmlspecialchars_uni($e["ip"])) . ':<span class="fw-bold">' . (int)$e['port'] . '</span>
			</small>
			
			  <span><i class="bi bi-arrow-up text-success"></i> ' . mksize($e["uploaded"] ?? 0) . '</span>
                                <span><i class="bi bi-arrow-down text-info"></i> ' . mksize($e["downloaded"] ?? 0) . '</span>
			
			</div>';

        // User display из старого кода
        $user_display = empty($e["username"])
            ? '<span class="text-muted"><i class="bi bi-question-circle me-2"></i>Unknown</span>'
            : ($is_mod || $torrent['anonymous'] != 'yes' || $e['id'] != $torrent['owner']
                ? '<div class="d-flex align-items-center">
                      <div>
                          <a href="' . get_profile_link($e['userid']) . '" class="text-decoration-none fw-bold text-dark" data-bs-toggle="tooltip" title="View profile">' . get_user_color($e["username"], $e["namestyle"]) . '</a>
                      </div>
                   </div>
				   
				    
				   
				   
				   ' . $ip_display
				   
				 
				   
				   
                : '<span class="text-muted"><i class="bi bi-incognito me-2"></i>Anonymous</span>');

        // Получаем аватар для обычных пользователей
        $avatar_html = '';
        if (!empty($e["username"]) && ($is_mod || $torrent['anonymous'] != 'yes' || $e['id'] != $torrent['owner'])) {
            $useravatar = format_avatar($e['avatar'] ?? '', $e['avatardimensions'] ?? '', '50x50');
            
            if (strpos($useravatar['image'] ?? '', '<') === 0) {
                $avatar_html = str_replace('avatar-ring2', 'avatar-ring2 avatar-placeholder-custom', $useravatar['image'] ?? '');
            } else {
                $avatar_html = '<img class="avatar-image-custom rounded" src="' . ($useravatar['image'] ?? '') . '" alt="" ' . ($useravatar['width_height'] ?? 'width="50" height="50"') . ' />';
            }
        }
		
		
		$agent = (string)($e["agent"] ?? '');
        $peer_id = (string)($e["peer_id"] ?? '');
		
		$agent_info = get_agent_info($agent, $peer_id);
        $client_display = get_agent_html($agent, $peer_id, true);
		
		
		$secs = max(1, ($now - $e["st"]) - ($now - $e["la"]));
        $upload_rate = mksize(($e["uploaded"] - ($e["uploadoffset"] ?? 0)) / $secs) . '/s';
		
		
		$download_rate = $e["seeder"] == "no"
            ? mksize(($e["downloaded"] - ($e["downloadoffset"] ?? 0)) / $secs) . '/s'
            : mksize(($e["downloaded"] - ($e["downloadoffset"] ?? 0)) / max(1, ($e["finishedat"] ?? $now) - $e['st'])) . '/s';
		
		
		$download_rate_display = '<span class="badge bg-danger bg-opacity-75 text-white"><i class="bi bi-speedometer2 me-1"></i>' . $download_rate . '</span>';
		$upload_rate_display = '<span class="badge bg-success bg-opacity-75 text-white"><i class="bi bi-speedometer me-1"></i>' . $upload_rate . '</span>';
		
		
		// Ratio display
        if ($e["downloaded"]) {
            $ratio = floor(($e["uploaded"] / $e["downloaded"]) * 1000) / 1000;
            $ratio_color = get_ratio_color($ratio);
            $ratio_icon = $ratio >= 1.0 ? 'bi-arrow-up-right-circle' : ($ratio >= 0.5 ? 'bi-arrow-right-circle' : 'bi-arrow-down-right-circle');
            $ratio_display = '<span class="d-flex align-items-center justify-content-end"><i class="bi ' . $ratio_icon . ' me-2" style="color: ' . $ratio_color . '"></i><span class="fw-bold" style="color: ' . $ratio_color . '">' . number_format($ratio, 2) . '</span></span>';
        } elseif ($e["uploaded"]) {
            $ratio_display = '<span class="badge bg-success"><i class="bi bi-infinity me-1"></i>∞</span>';
        } else {
            $ratio_display = '<span class="text-muted">---</span>';
        }
		
		
		
		
		
		
		
        
        $s .= '
        <div class="peer-card mb-3 p-3 bg-white border ' . $card_highlight . '">
            <div class="d-flex align-items-start">
                <!-- Аватар или иконка -->
                <div class="me-3 position-relative">
                    ' . ($avatar_html ?: '<div class="avatar-placeholder-custom"><i class="bi bi-person"></i></div>') . '
                    ' . ($CURUSER["id"] == $e["id"] ? '<span class="current-user-badge">You</span>' : '') . '
                </div>
                
                <!-- Информация -->
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="w-100">
                            ' . $user_display . '
                            
                            
                                
                                
								<div class="d-flex gap-3 small text-secondary mt-2">
								
								
								
								
								
								
								
								<div>
                            <span class="text-secondary d-block"><i class="bi bi-speedometer me-2"></i>Up Rate</span>
							
							
							
							
                            '.$upload_rate_display.'
                        </div>
                        <div>
                            <span class="text-secondary d-block"><i class="bi bi-speedometer2 me-2"></i>Down Rate</span>
                            '.$download_rate_display.'
                        </div>
						
						
						 <div>
                            <span class="text-secondary d-block"><i class="bi bi-percent me-2"></i>Ratio</span>
                            '.$ratio_display.'
                        </div>
						
						
                        <div>
                            <span class="text-secondary d-block"><i class="bi bi-clock-history me-2"></i>Connected</span>
                            <span class="fw-bold">' . mkprettytime($now - $e["st"]) . '</span>
                        </div>
                        <div>
                            <span class="text-secondary d-block"><i class="bi bi-hourglass-split me-2"></i>Idle</span>
                            <span class="fw-bold">' . mkprettytime($now - $e["la"]) . '</span>
                        </div>
						
						
						 <div>
                            <span class="text-secondary d-block"><i class="bi bi-laptop me-2"></i>Client</span>
                            <span class="fw-bold">' . $client_display . '</span>
                        </div>
								
								
								
								
                            </div>
                        </div>
                        
                       <!-- Connectable badge -->
                           <div class="text-end ms-3">
                            <span class="badge ' . ($e['connectable'] == 'yes' ? 'bg-success' : 'bg-danger') . ' rounded-0 px-3 py-2">
                                ' . ($e['connectable'] == 'yes' ? 'CONNECTABLE' : 'FIREWALLED') . '
                            </span>

							
							
						<!-- Прогресс бар -->
                    <div class="mt-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Progress</span>
                            <span class="fw-bold">' . sprintf("%.1f%%", $progress) . '</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-' . ($is_seeders ? 'success' : 'info') . '" style="width: ' . $progress . '%"></div>
                        </div>
                    </div>


						
							
							
                        </div>
                    </div>
                    
                    
                    
                    <!-- Детали -->
                    <div class="d-flex flex-wrap gap-4 mt-3 small">
                        
                        
                    </div>
                </div>
            </div>
        </div>';
    }
    
    $s .= '</div>';
    
    $s .= '<style>
        .peers-grid { 
            max-height: 600px; 
            overflow-y: auto; 
            padding-right: 8px; 
        }
        .peers-grid::-webkit-scrollbar {
            width: 6px;
        }
        .peers-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .peers-grid::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .peers-grid::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .peer-card { 
            transition: all 0.2s ease; 
            border-left: 3px solid transparent; 
            border-radius: 8px;
        }
        .peer-card:hover { 
            transform: translateX(5px) translateY(-2px); 
            border-left-color: #0d6efd; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .current-user-card {
            background: linear-gradient(to right, rgba(13, 110, 253, 0.02), transparent);
        }
        .avatar-image-custom { 
            width: 50px; 
            height: 50px; 
            border-radius: 12px; 
            object-fit: cover; 
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .avatar-placeholder-custom {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #667eea;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .current-user-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #0d6efd;
            color: white;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
		
		.anonymous-avatar-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e9ecef;
            color: #adb5bd;
        }
		
        .badge {
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        @media (max-width: 768px) {
            .peer-card .d-flex {
                flex-direction: column;
            }
            .peer-card .me-3 {
                margin-right: 0 !important;
                margin-bottom: 15px;
            }
            .d-flex.gap-4 {
                gap: 10px !important;
                flex-wrap: wrap;
            }
        }
    </style>';
    
    return $p . $s;
}