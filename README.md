# 🎬 PHP Torrent Tracker

[![PHP](https://img.shields.io/badge/PHP-8.5-777bb3?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-9.7_LTS-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Composer](https://img.shields.io/badge/Composer-Required-885630?logo=composer&logoColor=white)](https://getcomposer.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> ⚡ A private **BitTorrent tracker** with a built-in forum, on a heavily reworked **MyBB 1.8** core:
> announce with anti-cheat, torrent parser (BitTorrent v1 + v2), AJAX chat, reactions,
> notifications, requests & offers, and a full staff panel. English and Russian out of the box.

Running live at **[ArtCore Gangsta](https://artcore-gangsta.eu)**. Made for **private communities**,
testing torrent workflows, or learning how a tracker works under the hood.

---

## 📸 Screenshots

### 🔹 Main Page
![Main Page](docs/3.png)
![Main Page](docs/4.png)

### 🔹 Browse Torrents
![Browse Torrents](docs/2.png)

### 🔹 Upload Page
![Upload Page](docs/5.png)

### 🔹 Forum
![Forum](docs/11.jpeg)

### 🔹 Staff Panel
![Staff Panel](docs/9.jpeg)

---

## ✨ Features

**Tracker**
- 🔗 Announce endpoint with real-time anti-cheat checks plus a cron-based cheat detector
- 📂 Torrent parsing via [arokettu/torrent-file](https://github.com/arokettu/torrent-file), **BitTorrent v2 (BEP 52)** info-hash support
- 🧲 Magnet links for external / cross-seeded torrents
- 🎁 Promotions: Free, Silver, 2× upload, 30% leech — rolled automatically on upload or set by staff
- 🎯 Hit & Run with automatic warnings and bans, VIP / staff exemptions
- 🏷️ Claims (keep-seeding commitments) and seed bonus points with a full bonus log
- 🙋 **Requests & Offers** with votes, bounties and auto-matching to new uploads
- 🎓 Exams / tasks for new members
- 🎬 IMDb card on torrent pages
- ⭐ 10-star ratings for torrents and forum threads

**Community**
- 💬 **AJAX chat**: replies, @mentions with sound, private 1:1 dialogs with block list and reports, pinned message, staff archive & search
- 🤖 **Chat bot** posts new torrents (as embedded torrent cards), requests and offers — each event switchable in settings
- 😀 **Reactions** with the site's own smilies on chat messages, torrent comments and forum posts
- 🔔 **Notification center** (header bell): replies, mentions, filled requests, awards, subscriptions
- 🗨️ Forum with attachments, BBCode editor, `[torrent=ID]` cards, `[spoiler]`, `[nfo]`, tables
- 🌍 Full **English / Russian** interface, switchable per user

**Security**
- 🔐 TOTP 2FA for staff with backup codes; email alerts on 2FA changes and new-device logins
- 🖥️ Known-devices tracking and session management ("log out everywhere")
- 🛡️ CSRF tokens on every POST / AJAX action, prepared statements only, bcrypt passwords
- 🕵️ Fake-staff detection and auto-expiring IP bans

**Staff Panel**
- 📈 Dashboard with KPI tiles and stats; activity heatmap
- 👤 Per-user tools: activity log, security, hit & run history, audit log, usage charts
- 🧰 Upload / download / ratio managers, mass reseed, comment moderation (move / copy / merge)
- ⚙️ Settings editor that logs every change; cron manager; language file checker

---

## 🧩 About the Source

### Origins
The forum side (parser, data handlers, sessions, moderation, templates logic) comes from **MyBB 1.8**,
rewritten for PHP 8.5 and merged with the tracker into one site. Several tracker features
(claims, exams / tasks, promotion odds) are adapted from **NexusPHP**. Everything is plain PHP —
no framework, no template engine, no MyBB plugin system in use.

### Tech stack
| Part | What is used |
|---|---|
| Backend | PHP 8.5, `declare(strict_types=1)` everywhere, `match`, typed signatures |
| Database | MySQL 9.7 via MySQLi, **prepared statements only** (`$db->sql_query_prepared()`) |
| Front-end | Bootstrap 5 (CSS variables, dark theme), vanilla JS — no jQuery |
| Icons / UI | Font Awesome 6 Free, Bootstrap Icons, SweetAlert2 |
| Composer packages | [`arokettu/torrent-file`](https://github.com/arokettu/torrent-file) (torrent parsing), [`erusev/parsedown`](https://github.com/erusev/parsedown) (Markdown) |

### Project layout
```
/                   public pages: index.php (tracker), index2.php (forum), browse.php, details.php,
                    upload.php, showthread.php, chat.php, requests.php, offers.php, usercp.php …
announce.php        tracker announce — reads only include/config_announce.php, never the settings table
scrape.php          scrape endpoint
xmlhttp.php         AJAX router: comments, ratings, chat_* actions, reactions
cron.php            cron runner for include/cron/*.php
admin/              staff panel (admin/index.php + one file per tool), its CSS in admin/templates/, JS in admin/scripts/
include/            core: init.php, functions*.php, class_*.php, db_mysqli.php, datahandlers/
include/cron/       scheduled jobs: cleanups, seed bonus, promotions, exams, claims, chat & notification cleanup
include/languages/  english/ and russian/ — one <page>.lang.php per page
include/templates/  header / footer and all page stylesheets (include/templates/default/style/)
scripts/            page JavaScript
sql/                install SQL: default_settings, install_data, faq, group
config/             legacy serialized config (THEME, HITRUN)
cache/              data cache files
torrents/           uploaded .torrent files
uploads/            avatars, attachments, editor images
```

### How it fits together
- **Settings** live in the `settings` table and are cached to `include/settings.php` (`rebuild_settings()`),
  so every page reads them as plain globals. The announce gets its own small cache, `include/config_announce.php`,
  to stay fast.
- **Languages**: `$lang->load('details')` loads `include/languages/<user language>/details.lang.php`;
  strings are used as `$lang->details['key']`. The user's language is stored in `users.language`.
- **Cron**: `cron.php` runs the jobs that are due (pseudo-cron from page footers); jobs and their intervals
  are managed in the staff panel.
- **User groups** are fixed IDs: 1 Guest, 2 User, 3 Power User, 4 VIP, 5 Uploader, 6 Moderator,
  7 Administrator, 8 SysOp, 9 Banned. Moderation is by group, there are no per-forum moderators.
- **Staff pages** are opened through `admin/index.php` (`STAFF_PANEL`), every change is POST + CSRF +
  Post/Redirect/Get and written to the site log.

---

## ⚡ Installation

1. Upload all files to your web server.
2. Install the PHP packages: run `composer install`, **or** unpack the bundled `vendor.rar` into the site root.
3. Open `https://yoursite.com/install.php` and follow the wizard:
   - ✅ Requirements check
   - 🗄️ Database configuration
   - 🌐 Site settings
   - 👤 Admin account creation
   - 🚀 One-click install
4. Delete `install.php` (and keep `sql/` closed from the web) after installation.

The installer writes `.htaccess` rules for Apache. On **nginx** or **IIS**, block web access to
`sql/`, `torrents/`, `cache/` and `uploads/` in the server config yourself — the last installer step shows examples.

---

## 📌 Requirements
- PHP **8.5** (developed on 8.4 / 8.5)
- MySQL **9.7 LTS**
- PHP extensions: `mysqli`, `mbstring`, `gd`, `openssl`, `curl`, `json`; recommended `fileinfo`, `ctype`, `zlib`
- Apache with `mod_rewrite` (nginx and IIS work with manual access rules)
- Composer (or the bundled `vendor.rar`)

## 📁 Directory Permissions
On Linux:
```bash
chmod 755 torrents/ uploads/ uploads/avatars/ cache/
chmod 666 include/config.php include/settings.php include/config_announce.php
```
On Windows, give the web server account write access to the same folders and files.

---

## 📜 License
[MIT](LICENSE) © Ruf790. Third-party parts keep their own licenses: MyBB (LGPL 3.0),
Bootstrap (MIT), Font Awesome Free (CC BY 4.0 / SIL OFL 1.1 / MIT), SweetAlert2 (MIT),
arokettu/torrent-file (MIT), Parsedown (MIT).
