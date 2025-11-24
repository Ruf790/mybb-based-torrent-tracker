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
    
    // Заголовок секции
    $p = '<div class="d-flex align-items-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                <i class="bi bi-people-fill text-primary fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">' . htmlspecialchars($name) . '</h4>
                <p class="text-muted mb-0"><i class="bi bi-person-check me-1"></i>' . htmlspecialchars((string)$totalcount) . ' active peers</p>
            </div>
          </div>';

    if ($totalcount <= 0) {
        $p .= '<div class="text-center py-5 bg-light rounded-3">
                 <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                 <p class="text-muted fs-5">No peers currently connected</p>
               </div>';
        return $p;
    }

    $now = TIMENOW;
    include_once(INC_PATH . '/functions_ratio.php');

    // Заголовки таблицы
    $table_headers = '';
    if ($is_seeders) {
        $userLabel       = '<i class="bi bi-person-circle me-2"></i>' . ($lang->global['user'] ?? 'User');
        $connectableLabel= '<i class="bi bi-wifi me-2"></i>' . ($lang->details['connectable'] ?? 'Connectable');
        $uploadedLabel   = '<i class="bi bi-cloud-arrow-up me-2"></i>' . ($lang->details['uploaded'] ?? 'Uploaded');
        $upRateLabel     = '<i class="bi bi-speedometer me-2"></i>' . ($lang->details['rate'] ?? 'Up Rate');
        $downloadedLabel = '<i class="bi bi-cloud-arrow-down me-2"></i>' . ($lang->details['downloaded'] ?? 'Downloaded');
        $downRateLabel   = '<i class="bi bi-speedometer2 me-2"></i>' . ($lang->details['rate'] ?? 'Down Rate');
        $ratioLabel      = '<i class="bi bi-percent me-2"></i>' . ($lang->details['ratio'] ?? 'Ratio');
        $completedLabel  = '<i class="bi bi-check-circle me-2"></i>' . ($lang->details['completed'] ?? 'Completed');
        $connectedLabel  = '<i class="bi bi-clock-history me-2"></i>' . ($lang->details['connected'] ?? 'Connected');
        $idleLabel       = '<i class="bi bi-hourglass-split me-2"></i>' . ($lang->details['idle'] ?? 'Idle');
        $clientLabel     = '<i class="bi bi-laptop me-2"></i>' . ($lang->details['client'] ?? 'Client');

        $table_headers = <<<HTML
        <thead class="bg-gradient-primary text-white">
            <tr>
                <th scope="col" class="ps-4 py-3 border-0">{$userLabel}</th>
                <th scope="col" class="text-center py-3 border-0">{$connectableLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$uploadedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$upRateLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$downloadedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$downRateLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$ratioLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$completedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$connectedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$idleLabel}</th>
                <th scope="col" class="text-start pe-4 py-3 border-0">{$clientLabel}</th>
            </tr>
        </thead>
HTML;
    }

    $s = '<div class="table-responsive rounded-3 shadow-sm border-0">';
    $s .= '<table class="table table-hover table-borderless mb-0" role="grid" aria-label="Peers Table">';
    $s .= $table_headers;
    $s .= '<tbody class="bg-white">';

    $num = 0;
    foreach ($arr as $e) {
        $num++;

        // Безопасное приведение значений
        $e["uploaded"]   = (int)($e["uploaded"] ?? 0);
        $e["downloaded"] = (int)($e["downloaded"] ?? 0);
        $e["to_go"]      = (int)($e["to_go"] ?? 0);
        $e["st"]         = (int)($e["st"] ?? 0);
        $e["la"]         = (int)($e["la"] ?? 0);
        $torrent["size"] = (int)($torrent["size"] ?? 1);

        // Agent безопасный
        $agent = (string)($e["agent"] ?? '');
        $peer_id = (string)($e["peer_id"] ?? '');

        if (!$is_mod && $CURUSER['id'] != $e['id']) {
            $s .= '<tr><td colspan="11" class="text-center py-4 text-muted">
                     <i class="bi bi-eye-slash display-6 opacity-50 mb-2 d-block"></i>
                     <span class="fs-5">Anonymous User</span>
                   </td></tr>';
            continue;
        }

        $highlight = $CURUSER["id"] == $e["id"]
            ? ' class="bg-success bg-opacity-10 border-start border-success border-4"'
            : ' class="border-start border-light border-4"';

        $ip_display = $is_mod
            ? '<div class="mt-1"><small class="badge bg-dark bg-opacity-25 text-dark"><i class="bi bi-globe me-1"></i>' . htmlspecialchars_uni($e["ip"]) . ':<span class="fw-bold">' . (int)$e['port'] . '</span></small></div>'
            : '<div class="mt-1"><small class="badge bg-light text-muted"><i class="bi bi-eye me-1"></i>' . preg_replace('/\.\d+\.\d+$/', '***', htmlspecialchars_uni($e["ip"])) . ':<span class="fw-bold">' . (int)$e['port'] . '</span></small></div>';

        $user_display = empty($e["username"])
            ? '<span class="text-muted"><i class="bi bi-question-circle me-2"></i>Unknown</span>'
            : ($is_mod || $torrent['anonymous'] != 'yes' || $e['id'] != $torrent['owner']
                ? '<div class="d-flex align-items-center">
                      <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                          <i class="bi bi-person text-primary"></i>
                      </div>
                      <div>
                          <a href="' . get_profile_link($e['userid']) . '" class="text-decoration-none fw-bold text-dark" data-bs-toggle="tooltip" title="View profile">' . get_user_color($e["username"], $e["namestyle"]) . '</a>
                      </div>
                   </div>' . $ip_display
                : '<span class="text-muted"><i class="bi bi-incognito me-2"></i>Anonymous</span>');

        $secs = max(1, ($now - $e["st"]) - ($now - $e["la"]));
        $upload_rate = mksize(($e["uploaded"] - ($e["uploadoffset"] ?? 0)) / $secs) . '/s';
        $download_rate = $e["seeder"] == "no"
            ? mksize(($e["downloaded"] - ($e["downloadoffset"] ?? 0)) / $secs) . '/s'
            : mksize(($e["downloaded"] - ($e["downloadoffset"] ?? 0)) / max(1, ($e["finishedat"] ?? $now) - $e['st'])) . '/s';

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

        $connect_icon = $e['connectable'] == 'yes' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
        $connect_label = $e['connectable'] == 'yes' ? 'Yes' : 'No';
        $connect_display = '<div class="d-flex align-items-center justify-content-center"><i class="bi ' . $connect_icon . ' me-2"></i><span class="fw-semibold">' . $connect_label . '</span></div>';

        $uploadedSize = '<span class="fw-bold text-success">' . mksize($e["uploaded"]) . '</span>';
        $downloadedSize = '<span class="fw-bold text-info">' . mksize($e["downloaded"]) . '</span>';
        $completedPerc = sprintf("%.2f%%", 100 * (1 - ($e["to_go"] / max(1, $torrent["size"]))));
        $progress_width = min(100, (1 - ($e["to_go"] / max(1, $torrent["size"]))) * 100);

        $completed_display = '
            <div class="d-flex align-items-center justify-content-end">
                <div class="me-3 text-end">
                    <span class="fw-bold">' . $completedPerc . '</span>
                </div>
                <div class="progress" style="width: 60px; height: 6px;">
                    <div class="progress-bar bg-success" style="width: ' . $progress_width . '%"></div>
                </div>
            </div>';

        $agent_info = get_agent_info($agent, $peer_id);
        $client_display = get_agent_html($agent, $peer_id, true);

        $startTime = '<span class="badge bg-primary bg-opacity-75 text-white"><i class="bi bi-clock-history me-1"></i>' . mkprettytime($now - $e["st"]) . '</span>';
        $lastActive = '<span class="badge bg-warning bg-opacity-75 text-white"><i class="bi bi-hourglass-split me-1"></i>' . mkprettytime($now - $e["la"]) . '</span>';
        $upload_rate_display = '<span class="badge bg-success bg-opacity-75 text-white"><i class="bi bi-speedometer me-1"></i>' . $upload_rate . '</span>';
        $download_rate_display = '<span class="badge bg-info bg-opacity-75 text-white"><i class="bi bi-speedometer2 me-1"></i>' . $download_rate . '</span>';

        $s .= <<<HTML
<tr{$highlight}>
    <td class="ps-4 py-3">{$user_display}</td>
    <td class="text-center py-3">{$connect_display}</td>
    <td class="text-end py-3">{$uploadedSize}</td>
    <td class="text-end py-3">{$upload_rate_display}</td>
    <td class="text-end py-3">{$downloadedSize}</td>
    <td class="text-end py-3">{$download_rate_display}</td>
    <td class="text-end py-3">{$ratio_display}</td>
    <td class="text-end py-3">{$completed_display}</td>
    <td class="text-end py-3">{$startTime}</td>
    <td class="text-end py-3">{$lastActive}</td>
    <td class="pe-4 py-3">{$client_display}</td>
</tr>
HTML;
    }

    $s .= '</tbody></table></div>';
    return $p . $s;
}
