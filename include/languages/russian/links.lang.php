<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// links.php
$language['links'] = array
(

// ── Page ──
'head'                    => 'Каталог торрент-клиентов',
'pane_title'              => 'Коллекция торрент-клиентов',
'pane_subtitle'           => 'Подробный каталог BitTorrent-клиентов под любую платформу и задачу',

// ── Stats ──
'stat_windows'            => 'Клиентов для Windows',
'stat_linux'              => 'Клиентов для Linux',
'stat_mac'                => 'Клиентов для Mac',
'stat_mobile'             => 'Мобильных приложений',

// ── Clients table ──
'sec_clients'             => 'Полный список клиентов',
'lbl_clients_badge'       => '70+ клиентов',
'lbl_col_name'            => 'Клиент',
'lbl_col_platform'        => 'Платформа',
'lbl_col_desc'            => 'Описание',
'lbl_col_status'          => 'Статус',
'lbl_col_action'          => 'Действие',
'btn_download'            => 'Скачать',
'btn_archive'             => 'Архив',

// ── Groups ──
'grp_windows'             => 'Клиенты для Windows',
'grp_linux'               => 'Клиенты для Linux',
'grp_macos'               => 'Клиенты для macOS',
'grp_mobile'              => 'Мобильные приложения',
'grp_legacy'              => 'Специальные и устаревшие клиенты',

// ── Status badges ──
'st_active'               => 'Активен',
'st_legacy'               => 'Устарел',
'st_limited'              => 'Ограничен',
'st_jailbreak'            => 'Нужен jailbreak',

// ── Client subtitles ──
'sub_open_source'         => 'Открытый код',
'sub_thin_client'         => 'Тонкий клиент',
'sub_minimalistic'        => 'Минимализм',

// ── Client descriptions ──
'desc_qbittorrent'        => 'Замена µTorrent: без рекламы, богатый функционал',
'desc_deluge'             => 'Лёгкий, модульный, кроссплатформенный',
'desc_transmission'       => 'Быстрый и лёгкий, родной для macOS и Linux',
'desc_utorrent'           => 'Самый популярный, лёгкий и шустрый',
'desc_bitcomet'           => 'Ветеран, множество функций',
'desc_vuze'               => 'На Java, продвинутые возможности',
'desc_tixati'             => 'Без рекламы, упор на приватность',
'desc_bittorrent'         => 'Оригинальный клиент от создателей BitTorrent',
'desc_frostwire'          => 'Gnutella + BitTorrent, открытый код',
'desc_halite'             => 'Открытый код, минималистичный дизайн',
'desc_rtorrent'           => 'Консольный, мощный и лёгкий',
'desc_ktorrent'           => 'Интеграция с KDE, богатый функционал',
'desc_transmission_qt'    => 'Qt-интерфейс для Transmission',
'desc_aria2'              => 'Консольный, поддерживает много протоколов',
'desc_tribler'            => 'Анонимный, децентрализованный, встроенный VPN',
'desc_folx'               => 'Менеджер загрузок в стиле Mac + торренты',
'desc_bitrocket'          => 'Родной для macOS, выборочная загрузка файлов',
'desc_tomato'             => 'Простой и аккуратный интерфейс для macOS',
'desc_webtorrent'         => 'Смотрите раздачу прямо во время загрузки',
'desc_flud'               => 'Material Design, мощный клиент для Android',
'desc_itransmission'      => 'Клиент для iOS, нужен jailbreak',
'desc_libretorrent'       => 'Открытый код, без рекламы, Android',
'desc_frostwire_mobile'   => 'Мобильная версия FrostWire',
'desc_bittornado'         => 'Экспериментальный клиент от TheSHAD0W',
'desc_abc'                => 'Улучшенный клиент BitTorrent, стабильный',
'desc_shareaza'           => 'Мультисетевой: Gnutella, eDonkey, BitTorrent',
'desc_maketorrent'        => 'Утилита для создания торрентов',
'desc_burst'              => 'Альтернативный клиент для Win32',
'desc_g3torrent'          => 'Графический клиент на Python',

// ── Top recommendations ──
'sec_recommendations'     => 'Лучший выбор',
'rec_badge_first'         => 'Выбор №1',
'rec_badge_light'         => 'Лёгкий',
'rec_badge_simple'        => 'Просто и быстро',
'rec_desc_qbittorrent'    => 'Открытый код, без рекламы, богатый функционал, все платформы',
'rec_desc_deluge'         => 'Тонкий клиент, модульные плагины, отлично подходит для сидбоксов',
'rec_desc_transmission'   => 'Минимализм, родной для macOS/Linux, почти не нагружает систему',
'chk_no_ads'              => 'Без рекламы',
'chk_open_source'         => 'Открытый код',
'chk_all_platforms'       => 'Все платформы',

// ── Choosing guide ──
'sec_guide'               => 'Как выбрать',
'guide_windows_title'     => 'Для Windows',
'guide_windows'           => 'qBittorrent или Deluge — без рекламы. µTorrent 2.2.1 — для старых систем.',
'guide_macos_title'       => 'Для macOS',
'guide_macos'             => 'Transmission — родной интерфейс. qBittorrent — те же возможности, что и на Windows.',
'guide_linux_title'       => 'Для Linux',
'guide_linux'             => 'Deluge — с графическим интерфейсом, rTorrent — для консоли. Transmission — для минимализма.',
'guide_privacy_title'     => 'Если важна приватность',
'guide_privacy'           => 'qBittorrent, Deluge или Tixati. µTorrent лучше избегать из-за рекламы и слежки.',

// ── Footer ──
'foot_warning'            => 'Скачивайте клиенты только с официальных сайтов. Избегайте модифицированных сборок с сомнительных ресурсов.',
'foot_updated'            => 'Обновлено: {1} | Всего клиентов в списке: 70+',
'fmt_month'               => 'm.Y',

);
