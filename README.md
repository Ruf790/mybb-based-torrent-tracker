# 🎬 PHP Torrent Tracker

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Composer](https://img.shields.io/badge/Composer-Required-885630?logo=composer&logoColor=white)](https://getcomposer.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A lightweight torrent tracker built with PHP.  
Includes announce system, torrent parsing library, and admin panel.  

---

## ⚡️ Installation

### 1. Import Database
Upload the SQL file from the folder:


admin/backup

and import it into your MySQL database.

### 2. Install Dependencies
This project uses the **[arokettu/torrent-file](https://github.com/arokettu/torrent-file)** library.  
Install via Composer:

composer require arokettu/torrent-file


### 3. Configure Database
Edit `include/config.php`:
```php
$config['database']['database'] = 'dbname';     // Database name
$config['database']['hostname'] = 'localhost'; // Database host
$config['database']['username'] = 'user';      // Database user
$config['database']['password'] = 'password';  // Database password


Configure Announce

Edit include/config_announce.php:

$mysql_host = '';  
$mysql_user = '';  
$mysql_pass = '';  
$mysql_db   = '';  

$BASEURL = 'https://localhost';  // Tracker URL
$SITENAME = 'Tracker Name';      // Tracker name


Configure Site Settings

Edit include/settings.php:


$SITENAME = "Tracker Name";  
$BASEURL = "https://localhost";  
$cookiedomain = ".localhost";  
$announce_urls[] = "https://localhost/announce.php";


Default Admin User

Username: Admin
Password: 123456

Ready!

After completing the steps above, your torrent tracker should be up and running.


📌 Requirements

PHP 7.4+

MySQL 5.7+ or MariaDB

Composer
