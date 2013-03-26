<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/24
 * Time: 23:59
 * To change this template use File | Settings | File Templates.
 */

require_once "config.php"
require_once "steam-condenser-php/lib/steam-condenser.php";
require_once "GeoIP/php-1.12/geoip.inc";

/**
 * Class GameServerManager
 */
class GameServerManager {

    /**
     * @var GameServerManager
     */
    private static $sharedInstance = null;

    /**
     * @return GameServerManager
     */
    public static function sharedManager() {
        if (!self::$sharedInstance) {
            self::$sharedInstance = new self();
        }
        return self::$sharedInstance;
    }

    /**
     * @var PDO
     */
    private static $sqlConnection = null;

    /**
     * @return PDO
     */
    private static function getSqlConnection() {
        if (!self::$sqlConnection) {
            $options = array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            );
            self::$sqlConnection = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST, DB_NAME, DB_PASS, $options);
        }
        return self::$sqlConnection;
    }

    private function __construct() {

    }
}