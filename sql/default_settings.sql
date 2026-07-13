-- Default Settings
-- Placeholders replaced by installer at runtime:
--   {{SITENAME}}        — site name
--   {{BASEURL}}         — site URL (no trailing slash)
--   {{SITEEMAIL}}       — site email
--   {{PRIVATEPATCH}}    — yes / no
--   {{TIMEZONE}}        — numeric offset, e.g. 2
--   {{COOKIEDOMAIN}}    — .example.com
--   {{ANNOUNCE_URL}}    — https://example.com/announce.php
--   {{SMTP_HOST}}       — smtp.gmail.com
--   {{SMTP_USER}}       — user@gmail.com
--   {{SMTP_PASS}}       — smtp password
--   {{SMTP_PORT}}       — 587
--   {{DB_HOST}}         — localhost
--   {{DB_USER}}         — db username
--   {{DB_PASS}}         — db password
--   {{DB_NAME}}         — db name

INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
-- ── Site ──────────────────────────────────────────────────────────────────────
('SITENAME',              '{{SITENAME}}'),
('BASEURL',               '{{BASEURL}}'),
('SITEEMAIL',             '{{SITEEMAIL}}'),
('contactemail',          '{{SITEEMAIL}}'),
('SITEONLINE',            'yes'),
('privatetrackerpatch',   '{{PRIVATEPATCH}}'),
('slogan',                'The Best Tracker'),
('metakeywords',          'torrent, tracker'),
('metadesc',              '{{SITENAME}} - Torrent Tracker'),
('charset',               'UTF-8'),
('shoutboxcharset',       'UTF-8'),
('defaultlanguage',       'english'),
('seourls',               'yes'),

-- ── Security ──────────────────────────────────────────────────────────────────
('encryption_key',        '{{ENCRYPTION_KEY}}'),
('aggressivecheckip',     'no'),
('maxloginattempts',      '7'),
('failedlogincount',      '3'),
('failedlogintext',       '1'),
('requirecomplexpasswords','0'),
('username_method',       '0'),

-- ── Cookies & Session ─────────────────────────────────────────────────────────
('cookiedomain',          '{{COOKIEDOMAIN}}'),
('cookiepath',            '/'),
('cookieprefix',          ''),
('cookiesecureflag',      '0'),
('cookiesamesiteflag',    '1'),

-- ── Date & Time ───────────────────────────────────────────────────────────────
('timezoneoffset',        '{{TIMEZONE}}'),
('dstcorrection',         '1'),
('dateformat',            'l, jS F, Y'),
('timeformat',            'h:i A'),
('regdateformat',         'M Y'),
('datetimesep',           ', '),

-- ── Tracker Core ──────────────────────────────────────────────────────────────
('announce_urls[]',       '{{ANNOUNCE_URL}}'),
('torrent_dir',           'torrents'),
('snatchmod',             'yes'),
('usezip',                'no'),
('gzipcompress',          'no'),
('ts_perpage',            '20'),
('loadlimit',             ''),
('wolcutoffmins',         '15'),
('use_xmlhttprequest',    '1'),
('redirects',             '0'),

-- ── Uploads & Avatars ─────────────────────────────────────────────────────────
('uploadspath',           './uploads'),
('avataruploadpath',      './uploads/avatars'),
('avatarsize',            '250000'),
('useravatar',            'pic/default_avatar.gif'),
('useravatardims',        '200|200'),
('maxavatardims',         '200x200'),
('allowremoteavatars',    '1'),
('pic_base_url',          'pic/'),
('enableattachments',     '1'),
('attachthumbh',          '96'),
('attachthumbw',          '96'),
('attachthumbnails',      'yes'),

-- ── Forum ─────────────────────────────────────────────────────────────────────
('f_postsperpage',        '10'),
('f_threadsperpage',      '20'),
('threadreadcut',         '7'),
('delayedthreadviews',    '1'),
('showforumpagesbreadcrumb','1'),
('browsingthisthread',    '1'),
('showownunapproved',     '1'),
('userpppoptions',        '5,10,15,20,25,30,40,50'),
('usertppoptions',        '10,15,20,25,30,40,50'),
('maxmultipagelinks',     '5'),
('jumptopagemultipage',   '1'),
('enablepms',             '1'),

-- ── Mail / SMTP ───────────────────────────────────────────────────────────────
('mail_handler',          'smtp'),
('smtp_host',             '{{SMTP_HOST}}'),
('smtp_user',             '{{SMTP_USER}}'),
('smtp_pass',             '{{SMTP_PASS}}'),
('smtp_port',             '{{SMTP_PORT}}'),
('secure_smtp',           '2'),
('mail_logging',          '2'),
('mail_message_id',       '1'),
('mail_queue_limit',      '10'),

-- ── Registration ──────────────────────────────────────────────────────────────
('regtype',               'instant'),
('disableregs',           '0'),
('minnamelength',         '6'),
('maxnamelength',         '30'),
('minpasswordlength',     '6'),
('maxpasswordlength',     '30'),
('maxusers',              '5000'),
('illegalusernames',      ''),
('_d_usergroup',          '2'),
('betweenregstime',       '24'),
('maxregsbetweentime',    '2'),
('offline_minutes',       ''),

-- ── Announce ──────────────────────────────────────────────────────────────────
('nc',                    'no'),
('bannedclientdetect',    'no'),
('checkconnectable',      'no'),
('checkip',               'no'),
('announce_wait',         '0'),
('announce_interval',     '900'),
('max_rate',              '2097152'),
('allowed_clients',       '-UT1610-,-AZ3034-,-UT1750-'),

-- ── DB (for announce / cron scripts) ─────────────────────────────────────────
('mysql_host',            '{{DB_HOST}}'),
('mysql_user',            '{{DB_USER}}'),
('mysql_pass',            '{{DB_PASS}}'),
('mysql_db',              '{{DB_NAME}}'),

-- ── Promotions / Demotions ────────────────────────────────────────────────────
('max_dead_torrent_time', '2'),
('promote_gig_limit',     '25'),
('promote_min_ratio',     '1.05'),
('promote_min_reg_days',  '28'),
('demote_min_ratio',      '0.95'),
('referrergift',          '2.5'),
('ban_user_limit',        '5'),
('invite_count',          '1'),
('autogigsignup',         '11'),
('autosbsignup',          '500'),

-- ── Leech Warning ─────────────────────────────────────────────────────────────
('leechwarn_min_ratio',   '0.4'),
('leechwarn_gig_limit',   '5'),
('leechwarn_length',      '2'),
('leechwarn_remove_ratio','0.8'),

-- ── Hit & Run ─────────────────────────────────────────────────────────────────
('hitrun',                'no'),
('hitrun_ratio',          '0.4'),
('hitrun_gig',            '7'),

-- ── Seed Bonus (KPS) ──────────────────────────────────────────────────────────
('bonus',                 'enable'),
('kpsupload',             '15.0'),
('kpscomment',            '5.0'),
('kpsthanks',             '3.0'),
('kpsrate',               '3.0'),
('kpspoll',               '2.0'),
('kpsmaxpoint',           '999.0'),
('kpsinvite',             'yes'),
('kpstitle',              'yes'),
('kpsvip',                'yes'),
('kpsgift',               'yes'),
('kpswarning',            'yes'),
('kpsratiofix',           'yes'),
('bdayreward',            'yes'),
('bdayrewardtype',        'freeleech'),

-- ── Promotions (random / timed) ───────────────────────────────────────────────
('prorules',              'yes'),
('randomhalfleech',       '7'),
('randomfree',            '2'),
('randomtwoup',           '2'),
('randomtwoupfree',       '1'),
('randomtwouphalfdown',   '0'),
('randomthirtypercentdown','0'),
('largesize',             '12'),
('largepro',              '5'),
('expirehalfleech',       '70'),
('expirefree',            '60'),
('expiretwoup',           '60'),
('expiretwoupfree',       '30'),
('expiretwouphalfleech',  '30'),
('expirethirtypercentleech','30'),
('expirenormal',          '0'),
('halfleechbecome',       '1'),
('freebecome',            '1'),
('twoupbecome',           '1'),
('twoupfreebecome',       '1'),
('twouphalfleechbecome',  '1'),
('thirtypercentleechbecome','1'),
('normalbecome',          '1'),

-- ── Hot Torrents ──────────────────────────────────────────────────────────────
('hotdays',               '7'),
('hotseeder',             '5'),

-- ── Misc ──────────────────────────────────────────────────────────────────────
('uploaderdouble',        'no'),
('deldeadtorrent',        'no');
