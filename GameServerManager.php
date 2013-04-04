<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/24
 * Time: 23:59
 * To change this template use File | Settings | File Templates.
 */

require_once "config.php";
require_once "steam-condenser-php/lib/steam-condenser.php";
require_once "GeoIP/php-1.12/geoip.inc";

// error handler
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    if ($errno == E_USER_NOTICE) {
        return true;
    }
    return false;
}
// set error handler
$old_error_handler = set_error_handler("myErrorHandler");

/**
 * Class GameServerManager
 */
class GameServerManager {

    const GAME_SERVER_ADDED = 'GAME_SERVER_ADDED';
    const GAME_SERVER_UPDATED = 'GAME_SERVER_UPDATED';

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
    private $sqlConnection = null;

    /**
     * @return PDO
     */
    private function getSqlConnection() {
        if (!$this->sqlConnection) {
            $options = array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            );
            $this->sqlConnection = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST, DB_NAME, DB_PASS, $options);
        }
        return $this->sqlConnection;
    }

    /**
     * @var GeoIP
     */
    private static $geoIp;

    /**
     * @return GeoIP
     */
    private static function getGeoIp() {
        if (!self::$geoIp) {

            self::$geoIp = geoip_open("GeoIP/GeoIP.dat",GEOIP_STANDARD);
        }

        return self::$geoIp;
    }

    private function __construct() {
    }

    /**
     * Add a server.
     * If the server already exists, update no_response_counter to 0.
     * @param string $ipAddress
     * @param number $portNo
     * @return string
     */
    public function addGameServer($ipAddress, $portNo) {
        $connection = $this->getSqlConnection();

        // check already registered
        $sql = 'SELECT * FROM  `game_servers` WHERE  `ip` = :ip AND  `query_port` = :query_port';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $statement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        $updateTime = time();

        // set no_response_counter to 0 if the record exist
        if ($result) {
            $gameServerId = $result['game_server_id'];
            $sql = 'UPDATE `game_servers` SET `no_response_counter` = 0, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id';
            $statement = $connection->prepare($sql);
            $statement->bindParam(':game_server_update', $updateTime, PDO::PARAM_INT);
            $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
            $statement->execute();
            return self::GAME_SERVER_UPDATED;
        }

        // insert a new record
        $country = geoip_country_code_by_addr(self::getGeoIp(), $ipAddress);
        $sql = 'INSERT INTO `game_servers` (`ip`, `country`, `query_port`, `no_response_counter`, `game_server_update`)
         VALUES (:ip, :country, :query_port, 0, :game_server_update)';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $statement->bindParam(':country', $country, PDO::PARAM_STR);
        $statement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
        $statement->bindParam(':game_server_update', $updateTime, PDO::PARAM_INT);
        $statement->execute();
        return self::GAME_SERVER_ADDED;
    }

    /**
     * @var MasterServer
     */
    private static $masterServerConnector;

    /**
     * @return MasterServer
     */
    private static function getMasterServerConnector() {
        if (!self::$masterServerConnector) {
            self::$masterServerConnector = new MasterServer(MasterServer::SOURCE_MASTER_SERVER);
        }
        return self::$masterServerConnector;
    }

    /**
     * Update server list with Steam Master Server
     * @return int number of servers
     */
    public function updateWithMasterServer() {
        $masterServerConnector = self::getMasterServerConnector();
        $serverList = $masterServerConnector->getServers(MasterServer::REGION_ALL, "\\type\\d\\gamedir\\chivalrymedievalwarfare");

        foreach($serverList as $server) {
            $ipAddress = $server[0];
            $portNo = $server[1];
            $this->addGameServer($ipAddress, $portNo);
        }
        return count($serverList);
    }

    /**
     * Update each server info
     */
    public function updateIndividualServerInfo() {
        // getting server list from DB
        $connection = $this->getSqlConnection();
        $sql = 'SELECT * FROM `game_servers` WHERE `no_response_counter` < 3';
        $statement = $connection->prepare($sql);
        $statement->execute();

        // init
        $noResponseServerList = array();
        $sql = "UPDATE `game_servers` SET `server_name` = :server_name, `game_port` = :game_port, `map_name` = :map_name, `game_dir` = :game_dir, `game_desc` = :game_desc, `max_players` = :max_players, `number_of_players` = :number_of_players, `no_response_counter` = :no_response_counter, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id";
        $updateServerStatement = $connection->prepare($sql);
        $updateTime = time();

        // update each server info
        while ($gameServerRecord = $statement->fetch(PDO::FETCH_ASSOC)) {
            $ipAddress = $gameServerRecord['ip'];
            $queryPort = $gameServerRecord['query_port'];
            $country = $gameServerRecord['country'];

            if ($country != 'JP' && $country != 'KR') {
                //continue;
            }

            $players = null;
            $serverInfo = null;
            $srcServer = null;

            try {
                $srcServer = new GoldSrcServer($ipAddress, $queryPort);
                //$srcServer->initialize();
                //$players = $srcServer->getPlayers();
                $serverInfo = $srcServer->getServerInfo();
            } catch (Exception $e) {
                $noResponseServerList[] = $gameServerRecord;
                continue;
            }

            /*
            if ($gameServerRecord['no_response_counter'] == 0 &&
                $gameServerRecord['server_name'] == $serverInfo['serverName'] &&
                $gameServerRecord['game_port'] == $serverInfo['serverPort'] &&
                $gameServerRecord['map_name'] == $serverInfo['manName'] &&
                $gameServerRecord['game_dir'] == $serverInfo['gameDir'] &&
                $gameServerRecord['game_desc'] == $serverInfo['gameDesc'] &&
                $gameServerRecord['max_Players'] == $serverInfo['maxPlayers'] &&
                $gameServerRecord['number_of_players'] == $serverInfo['numberOfPlayers']
            ) {
                continue;
            }
            */

            $updateServerStatement->bindParam(':server_name', $serverInfo['serverName']);
            $updateServerStatement->bindParam(':game_port',  $serverInfo['serverPort']);
            $updateServerStatement->bindParam(':map_name',  $serverInfo['mapName']);
            $updateServerStatement->bindParam(':game_dir',  $serverInfo['gameDir']);
            $updateServerStatement->bindParam(':game_desc',  $serverInfo['gameDesc']);
            $updateServerStatement->bindParam(':max_players',  $serverInfo['maxPlayers']);
            $updateServerStatement->bindParam(':number_of_players',  $serverInfo['numberOfPlayers']);
            $updateServerStatement->bindValue(':no_response_counter',  0);
            $updateServerStatement->bindParam(':game_server_update',  $updateTime);
            $updateServerStatement->bindParam(':game_server_id', $gameServerRecord['game_server_id']);
            $updateServerStatement->execute();
        }

        // update the servers did not respond
        $sql = "UPDATE `game_servers` SET `no_response_counter` = :no_response_counter, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id";
        $updateServerStatement = $connection->prepare($sql);
        foreach ($noResponseServerList as $gameServerRecord) {
            $noResponseCounter = $gameServerRecord['no_response_counter'] + 1;
            $updateServerStatement->bindParam(':no_response_counter', $noResponseCounter);
            $updateServerStatement->bindParam(':game_server_update', $updateTime);
            $updateServerStatement->bindParam(':game_server_id', $gameServerRecord['game_server_id']);
            $updateServerStatement->execute();
        }
    }

    /**
     * @param array $countryCodeList
     * @return PDOStatement
     */
    private function getStatementFroServerList($countryCodeList) {
        $connection = $this->getSqlConnection();

        // all if no arg
        if (!$countryCodeList || count($countryCodeList) === 0) {
            $sql = 'SELECT * FROM `game_servers` ORDER BY `country`, `server_name` ASC';
            $statement = $connection->prepare($sql);
            return $statement;
        }

        //$sql = "SELECT * FROM `game_servers` WHERE `country` IN (\'JP\',\'KR\')";

        $placeNameList = array();
        for ($i = 0, $len = count($countryCodeList); $i < $len; $i++) {
            $placeName = ":country_code_$i";
            $placeNameList[$i] = $placeName;
        }
        $joinedPlaceName = implode(',', $placeNameList);
        $sql = "SELECT * FROM `game_servers` WHERE `country` IN ($joinedPlaceName)";
        $statement = $connection->prepare($sql);

        for ($i = 0, $len = count($countryCodeList); $i < $len; $i++) {
            $countryCode = $countryCodeList[$i];
            $placeName = $placeNameList[$i];
            $statement->bindValue($placeName, $countryCode, PDO::PARAM_STR);
        }
        return $statement;
    }

    /**
     * @param array $countryCodeList
     * @return array
     */
    public function getServerList($countryCodeList) {



    }
}