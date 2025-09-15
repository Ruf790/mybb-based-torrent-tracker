# 🎬 PHP Torrent Tracker

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Composer](https://img.shields.io/badge/Composer-Required-885630?logo=composer&logoColor=white)](https://getcomposer.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> ⚡ A lightweight **PHP-based torrent tracker** with announce system,  
> built-in torrent parser, and a simple but functional admin panel.  

Perfect for **private communities**, testing torrent workflows, or learning how trackers work under the hood.  

---

## 📸 Screenshots

👉 Save your screenshots in the folder: `docs/`  

### 🔹 Main Page
![Main Page](docs/screenshot-main.png)

### 🔹 Torrent Details
![Torrent Details](docs/screenshot-details.png)

### 🔹 Admin Panel
![Admin Panel](docs/screenshot-admin.png)

---

## ✨ Features
- 🔗 **Announce system** — fully working announce endpoint for torrents  
- 📂 **Torrent file parsing** via [arokettu/torrent-file](https://github.com/arokettu/torrent-file)  
- 🛠 **Admin panel** for managing users, torrents, and site settings  
- 🍪 **Cookie-based sessions** for authentication  
- 🎨 Simple, clean codebase — easy to customize and extend  

---

## ⚡ Installation

### 1. Import Database
Upload the SQL file from:
admin/backup

pgsql
Copy code
and import it into your MySQL/MariaDB database.

### 2. Install Dependencies
composer require arokettu/torrent-file

bash
Copy code

### 3. Configure Database
Edit `include/config.php`:
```php
$config['database']['database'] = 'dbname';
$config['database']['hostname'] = 'localhost';
$config['database']['username'] = 'user';
$config['database']['password'] = 'password';
4. Configure Announce
Edit include/config_announce.php:

php
Copy code
$mysql_host = '';
$mysql_user = '';
$mysql_pass = '';
$mysql_db   = '';

$BASEURL = 'https://localhost';
$SITENAME = 'Tracker Name';
5. Configure Site Settings
Edit include/settings.php:

php
Copy code
$SITENAME = "Tracker Name";
$BASEURL = "https://localhost";
$cookiedomain = ".localhost";
$announce_urls[] = "https://localhost/announce.php";
6. Default Admin User
makefile
Copy code
Username: Admin
Password: 123456
✅ Ready to Go
After completing these steps, your tracker should be up and running.
Please don’t kill me hahah 😅

📌 Requirements
PHP 7.4+

MySQL 5.7+ / MariaDB

Composer
