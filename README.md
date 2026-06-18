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

### 🔹 Main Page
![Main Page](docs/3.png)
![Main Page](docs/4.png)

### 🔹 Browse Torrent 
![Torrent Details](docs/2.png)

### 🔹 Upload page
![Upload Details](docs/5.png)


### 🔹Details Page
![Torrent Details](docs/11.jpeg)


### 🔹 Forum
![Admin Panel](docs/11.jpeg)


### 🔹 Admin Panel
![Admin Panel](docs/9.jpeg)

---

## ✨ Features
- 🔗 **Announce system** — fully working announce endpoint for torrents  
- 📂 **Torrent file parsing** via [arokettu/torrent-file](https://github.com/arokettu/torrent-file)  
- 🛠 **Admin panel** for managing users, torrents, and site settings  
- 🍪 **Cookie-based sessions** for authentication  
- 🎨 Simple, clean codebase — easy to customize and extend  

---

## ⚡ Installation

### Option A — Web Installer (Recommended)
1. Upload all files to your web server
2. Open `http://yoursite.com/install.php` in your browser
3. Follow the step-by-step setup wizard:
   - ✅ Requirements check
   - 🗄️ Database configuration
   - 🌐 Site settings
   - 👤 Admin account creation
   - 🚀 One-click install
4. Delete `install.php` after installation

---

## 📌 Requirements
- PHP 8.5+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- mod_rewrite (Apache) or equivalent

## 📁 Directory Permissions
```bash
chmod 755 torrents/
chmod 755 uploads/
chmod 755 uploads/avatars/
chmod 755 cache/
chmod 666 include/config.php
chmod 666 include/settings.php
chmod 666 include/config_announce.php
```
