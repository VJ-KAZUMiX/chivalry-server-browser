<?php
/**
 * rename this file to 'config.php' and setup following configurations
 */

mb_internal_encoding("utf-8");
//date_default_timezone_set('Asia/Tokyo');
ob_start("ob_gzhandler");

// the command for exec() to update each server
// register_argc_argv in php.ini must be enabled
define('EXEC_PHP', '/usr/local/php/5.2.17/bin/php /home/kazumix/www/chivalry/updateTargetServer.php');
//define('EXEC_PHP', 'C:\xampp\php\php-cgi.exe D:\KAZUMiX\docs\project\KAZUMiX\steammonitor\git\chivalry-server-browser\updateTargetServer.php');

define('HTTP_HOST', 'steammonitor');
define('HTTP_PATH', '/');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'steam');
define('DB_USER', 'steam');
define('DB_PASS', 'steam');

function get_country_assoc() {
    return require 'country-list/country/cldr/en/country.php';
}
