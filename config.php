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

switch ($_SERVER['SERVER_NAME']) {

    default:
        define('DB_HOST', 'localhost');
        define('DB_USER', 'steam');
        define('DB_PASS', 'steam');
        define('DB_NAME', 'steam');
        break;
}
