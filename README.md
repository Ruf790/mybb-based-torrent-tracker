Torrent Tracker Installation

Upload the SQL file from the admin/backup folder and import it into your database.

Install the PHP Torrent File Library:

composer require arokettu/torrent-file

Configure the Database:
Edit the include/config.php file:

$config['database']['database'] = 'dbname';  // Database name
$config['database']['hostname'] = 'localhost';  // Database host
$config['database']['username'] = 'user';  // Database username
$config['database']['password'] = 'password';  // Database password


Configure Announce Settings:
Edit the include/config_announce.php file:

$mysql_host = '';  // Database host
$mysql_user = '';  // Database username
$mysql_pass = '';  // Database password
$mysql_db = '';  // Database name
$BASEURL = 'https://localhost';  // Tracker URL
$SITENAME = 'Tracker Name';  // Tracker name


Set Site Settings:
Edit the include/settings.php file:

$SITENAME = "Tracker Name";  // Tracker name
$BASEURL = "https://localhost";  // Base URL of your tracker
$cookiedomain = ".localhost";  // Cookie domain
$announce_urls[] = "https://localhost/announce.php";  // Announce URL


Admin User:
Username: Admin
Password: 123456

After completing these steps, your torrent tracker should be ready to use.
