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

    private function addGameServer($ipAddress, $portNo) {
        $connection = self::getSqlConnection();

        // check already registered
        $sql = 'SELECT * FROM  `game_servers` WHERE  `ip` = :ip AND  `query_port` = :query_port';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $statement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        // set no_response_counter to 0 if the record exist
        if ($result) {
            $gameServerId = $result['game_server_id'];
            $sql = 'UPDATE `game_servers` SET `no_response_counter` = 0 WHERE `game_server_id` = :game_server_id';
            $statement = $connection->prepare($sql);
            $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
            $statement->execute();
            return;
        }

        // insert a new record
        $country = 'XX';
        $sql = 'INSERT INTO `game_servers` (`ip`, `country`, `query_port`, `no_response_counter`, `game_server_update`)
         VALUES (:ip, :country, :query_port, 0, :game_server_update)';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $statement->bindParam(':country', $country, PDO::PARAM_STR);
        $statement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
        $statement->bindParam(':game_server_update', time(), PDO::PARAM_INT);
        $statement->execute();
    }
}