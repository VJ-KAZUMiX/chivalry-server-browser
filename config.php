<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/24
 * Time: 17:38
 * To change this template use File | Settings | File Templates.
 */

mb_internal_encoding("utf-8");
//date_default_timezone_set('Asia/Tokyo');
ob_start("ob_gzhandler");

define('HTTP_SERVER_NAME', 'steammonitor');
define('HTTP_SERVER_PORT', 80);
define('HTTP_SERVER_PATH', '/');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'steam');
define('DB_USER', 'steam');
define('DB_PASS', 'steam');

function get_country_assoc() {
    require_once 'country-list/country/cldr/en/country.php';
}
