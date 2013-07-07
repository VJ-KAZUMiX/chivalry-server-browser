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
require_once "GeoIP/geoip-api-php/geoip.inc";

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
            $this->sqlConnection = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST, DB_USER, DB_PASS, $options);
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
    public static function getGeoIp() {
        if (!self::$geoIp) {

            self::$geoIp = geoip_open(dirname(__FILE__) . "/GeoIP/GeoIP.dat",GEOIP_STANDARD);
        }

        return self::$geoIp;
    }

    private function __construct() {
    }

    /**
     * Add a server.
     * If the server already exists, reset no_response_counter to 0.
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

        // reset no_response_counter to 0 if the record exist
        if ($result) {
            $gameServerId = $result['game_server_id'];
            $sql = 'UPDATE `game_servers` SET `no_response_counter` = 0, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id';
            $statement = $connection->prepare($sql);
            $statement->bindValue(':game_server_update', time(), PDO::PARAM_INT);
            $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
            $statement->execute();
            return self::GAME_SERVER_UPDATED;
        }

        // insert a new record
        $country = geoip_country_code_by_addr(self::getGeoIp(), $ipAddress);
        if (!$country) {
            // unknown or invalid region
            $country = 'ZZ';
        }
        $sql = 'INSERT INTO `game_servers` (`ip`, `country`, `query_port`, `no_response_counter`, `game_server_update`)
         VALUES (:ip, :country, :query_port, 0, :game_server_update)';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $statement->bindParam(':country', $country, PDO::PARAM_STR);
        $statement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
        $statement->bindValue(':game_server_update', time(), PDO::PARAM_INT);
        $statement->execute();
        return self::GAME_SERVER_ADDED;
    }

    /**
     * Add servers
     * If the server already exists, reset no_response_counter to 0.
     * @param array $gameServerList
     */
    public function addGameServers($gameServerList) {
        $connection = $this->getSqlConnection();

        $existServerIdList = array();

        // prepare for checking already registered
        $sql = 'SELECT * FROM  `game_servers` WHERE  `ip` = :ip AND  `query_port` = :query_port';
        $selectStatement = $connection->prepare($sql);

        // prepare for inserting a new record
        $sql = 'INSERT INTO `game_servers` (`ip`, `country`, `query_port`, `no_response_counter`, `game_server_update`) VALUES (:ip, :country, :query_port, 0, :game_server_update)';
        $insertStatement = $connection->prepare($sql);

        foreach ($gameServerList as $record) {
            $ipAddress = $record['ipAddress'];
            $portNo = $record['portNo'];

            // check already registered
            $selectStatement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
            $selectStatement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
            $selectStatement->execute();
            $result = $selectStatement->fetch(PDO::FETCH_ASSOC);

            // reset no_response_counter to 0 if the record exist
            if ($result) {
                $gameServerId = $result['game_server_id'];
                $existServerIdList[] = $gameServerId;
                continue;
            }

            // insert a new record
            $country = geoip_country_code_by_addr(self::getGeoIp(), $ipAddress);
            if (!$country) {
                // unknown or invalid region
                $country = 'ZZ';
            }
            $insertStatement->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
            $insertStatement->bindParam(':country', $country, PDO::PARAM_STR);
            $insertStatement->bindParam(':query_port', $portNo, PDO::PARAM_INT);
            $insertStatement->bindValue(':game_server_update', time(), PDO::PARAM_INT);
            $insertStatement->execute();
        }

        if (!count($existServerIdList)) {
            return;
        }

        // reset no_response_counter to 0 if the record exist

        $placeNameList = array();
        for ($i = 0, $len = count($existServerIdList); $i < $len; $i++) {
            $placeNameList[] = ":game_server_id_{$i}";
        }
        $placeName = implode(',', $placeNameList);

        $sql = "UPDATE `game_servers` SET `no_response_counter` = 0, `game_server_update` = :game_server_update WHERE `game_server_id` IN({$placeName});";
        $updateStatement = $connection->prepare($sql);
        $counter = 0;
        foreach ($existServerIdList as $gameServerId) {
            $updateStatement->bindParam(":game_server_id_{$counter}", $gameServerId, PDO::PARAM_INT);
            $counter++;
        }
        $updateStatement->bindValue(':game_server_update', time(), PDO::PARAM_INT);
        $updateStatement->execute();
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

        $listForUpdate = array();

        foreach($serverList as $server) {
            $ipAddress = $server[0];
            $portNo = $server[1];
            //$this->addGameServer($ipAddress, $portNo);
            $listForUpdate[] = array('ipAddress' => $ipAddress, 'portNo' => $portNo);
        }
        $this->addGameServers($listForUpdate);

        return count($serverList);
    }

    /**
     * (Unused)
     * Update each server info
     */
    public function updateAllServerInfo() {
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
            //$country = $gameServerRecord['country'];

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
     * Update the server info of game_server_id
     * @param int $gameServerId
     * @return bool
     */
    public function updateTargetServerInfo($gameServerId) {
        // get current info from DB
        $connection = $this->getSqlConnection();
        //$sql = 'SELECT * FROM `game_servers` WHERE `game_server_id` = :game_server_id';
        $sql = 'SELECT `game_server_id`, `ip`, `query_port`, `no_response_counter` FROM `game_servers` WHERE `game_server_id` = :game_server_id';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
        $statement->execute();

        // prepare
        $noResponse = false;
        $updateTime = time();
        $gameServerRecord = null;
        $serverInfo = null;
        $players = null;

        // update if the record exists
        if ($gameServerRecord = $statement->fetch(PDO::FETCH_ASSOC)) {
            $ipAddress = $gameServerRecord['ip'];
            $queryPort = $gameServerRecord['query_port'];
            //$country = $gameServerRecord['country'];

            try {
                $srcServer = new GoldSrcServer($ipAddress, $queryPort);
                //$srcServer->initialize();
                $serverInfo = $srcServer->getServerInfo();
                $players = $srcServer->getPlayers();
            } catch (Exception $e) {
                $noResponse = true;
            }
        } else {
            return false;
        }

        if (!$noResponse) {
            // the server responded
            //$sql = "UPDATE `game_servers` SET `server_name` = :server_name, `game_port` = :game_port, `map_name` = :map_name, `game_dir` = :game_dir, `game_desc` = :game_desc, `max_players` = :max_players, `number_of_players` = :number_of_players, `no_response_counter` = :no_response_counter, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id";
            $sql = "UPDATE `game_servers` SET `server_name` = :server_name, `game_port` = :game_port, `map_name` = :map_name, `max_players` = :max_players, `number_of_players` = :number_of_players, `no_response_counter` = :no_response_counter, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id";
            $updateServerStatement = $connection->prepare($sql);
            $updateServerStatement->bindParam(':server_name', $serverInfo['serverName']);
            $updateServerStatement->bindParam(':game_port',  $serverInfo['serverPort']);
            $updateServerStatement->bindParam(':map_name',  $serverInfo['mapName']);
            //$updateServerStatement->bindParam(':game_dir',  $serverInfo['gameDir']);
            //$updateServerStatement->bindParam(':game_desc',  $serverInfo['gameDesc']);
            $updateServerStatement->bindParam(':max_players',  $serverInfo['maxPlayers']);
            //$updateServerStatement->bindParam(':number_of_players',  $serverInfo['numberOfPlayers']);
            $updateServerStatement->bindValue(':number_of_players',  count($players));
            $updateServerStatement->bindValue(':no_response_counter',  0);
            $updateServerStatement->bindParam(':game_server_update',  $updateTime);
            $updateServerStatement->bindParam(':game_server_id', $gameServerRecord['game_server_id']);
            $updateServerStatement->execute();

            // update players
            //$this->updatePlayers($gameServerRecord['game_server_id'], $players);
        } else {
            // the servers did not respond, count up no_response_counter
            $sql = "UPDATE `game_servers` SET `no_response_counter` = :no_response_counter, `game_server_update` = :game_server_update WHERE `game_server_id` = :game_server_id";
            $updateServerStatement = $connection->prepare($sql);
            $noResponseCounter = $gameServerRecord['no_response_counter'] + 1;
            $updateServerStatement->bindParam(':no_response_counter', $noResponseCounter);
            $updateServerStatement->bindParam(':game_server_update', $updateTime);
            $updateServerStatement->bindParam(':game_server_id', $gameServerRecord['game_server_id']);
            $updateServerStatement->execute();
        }

        return true;
    }

    /**
     * @param int $gameServerId
     * @param array $players
     */
    private function updatePlayers($gameServerId, $players) {
        $currentSteamPlayerList = array();
        /** @var $steamPlayer SteamPlayer */
        foreach ($players as $steamPlayer) {
            $name = $steamPlayer->getName();
            $currentSteamPlayerList[$name] = $steamPlayer;
        }

        // get old players
        $connection = $this->getSqlConnection();
        $sql = 'SELECT * FROM `game_players` WHERE `game_server_id` = :game_server_id';
        $statement = $connection->prepare($sql);
        $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
        $statement->execute();

        // update matched player records
        $sql = "UPDATE `game_players` SET `player_connection_time` = :player_connection_time, `player_score` = :player_score, `player_update` = :player_update WHERE `game_player_id` = :game_player_id;";
        $updateStatement = $connection->prepare($sql);
        $sql = "DELETE FROM `game_players` WHERE `game_player_id` = :game_player_id";
        $deleteStatement = $connection->prepare($sql);
        while ($playerRecord = $statement->fetch(PDO::FETCH_ASSOC)) {
            $name = $playerRecord['player_name'];
            if (isset($currentSteamPlayerList[$name])) {
                // update
                $steamPlayer = $currentSteamPlayerList[$name];
                $updateStatement->bindValue(':player_connection_time', $steamPlayer->getConnectTime());
                $updateStatement->bindValue(':player_score', $steamPlayer->getScore());
                $updateStatement->bindValue(':player_update', time());
                $updateStatement->bindParam(':game_player_id', $playerRecord['game_player_id']);
                $updateStatement->execute();

                // remove updated player from the current list
                unset($currentSteamPlayerList[$name]);
            } else {
                // delete the old player record
                $deleteStatement->bindParam('game_player_id', $playerRecord['game_player_id']);
                $deleteStatement->execute();
            }
        }

        // insert new players
        $sql = "INSERT INTO `game_players` (`game_player_id`, `player_name`, `game_server_id`, `player_connection_time`, `player_score`, `player_update`) VALUES (NULL, :player_name, :game_server_id, :player_connection_time, :player_score, :player_update);";
        $insertStatement = $connection->prepare($sql);
        foreach ($currentSteamPlayerList as $steamPlayer) {
            $insertStatement->bindValue(':player_name', $steamPlayer->getName());
            $insertStatement->bindValue(':game_server_id', $gameServerId);
            $insertStatement->bindValue(':player_connection_time', $steamPlayer->getConnectTime());
            $insertStatement->bindValue(':player_score', $steamPlayer->getScore());
            $insertStatement->bindValue(':player_update', time());
            $insertStatement->execute();
        }
    }

    /**
     * @param array $countryCodeList
     * @return PDOStatement
     */
    private function getPDOStatementForServerList($countryCodeList = null) {
        $connection = $this->getSqlConnection();
        $unreponseThredhold = UNRESPONSE_THRESHOLD;

        // all if no arg
        if (!$countryCodeList || count($countryCodeList) === 0) {
            $sql = 'SELECT * FROM `game_servers` WHERE `no_response_counter` <= :no_response_counter ORDER BY `number_of_players` DESC, `server_name` ASC';
            $statement = $connection->prepare($sql);
            $statement->bindParam(':no_response_counter', $unreponseThredhold, PDO::PARAM_INT);
            return $statement;
        }

        //$sql = "SELECT * FROM `game_servers` WHERE `country` IN (\'JP\',\'KR\')";

        $placeNameList = array();
        for ($i = 0, $len = count($countryCodeList); $i < $len; $i++) {
            $placeName = ":country_code_$i";
            $placeNameList[$i] = $placeName;
        }
        $joinedPlaceName = implode(',', $placeNameList);
        $sql = "SELECT * FROM `game_servers` WHERE `no_response_counter` <= :no_response_counter AND `country` IN ($joinedPlaceName) ORDER BY `number_of_players` DESC, `server_name` ASC";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':no_response_counter', $unreponseThredhold, PDO::PARAM_INT);

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
    public function getServerList($countryCodeList = null) {
        $statement = $this->getPDOStatementForServerList($countryCodeList);
        $statement->execute();
        $serverList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $serverList;
    }

    /**
     * @param int $noResponseCounter
     */
    public function deleteUnrespondedServers($noResponseCounter = 3) {
        $connection = $this->getSqlConnection();
        $sql = "DELETE FROM `game_servers` WHERE `no_response_counter` >= :no_response_counter";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':no_response_counter', $noResponseCounter, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param int seconds
     */
    public function deleteUnrespondedServersWithTime($unresponseTimeSec) {
        $targetUpdateTime = time() - $unresponseTimeSec;
        $connection = $this->getSqlConnection();
        $sql = "DELETE FROM `game_servers` WHERE `game_server_update` <= :game_server_update";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':game_server_update', $targetUpdateTime, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param int $gameServerId
     * @return null|array
     */
    public function getServerInfo($gameServerId) {
        $connection = $this->getSqlConnection();
        $sql = "SELECT * FROM `game_servers` WHERE `game_server_id` = :game_server_id";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
        $statement->execute();

        $gameServerRecord = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$gameServerRecord) {
            return null;
        }

        $sql = "SELECT * FROM `game_players` WHERE `game_server_id` = :game_server_id ORDER BY `player_connection_time` DESC";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':game_server_id', $gameServerId, PDO::PARAM_INT);
        $statement->execute();

        $playerRecords = $statement->fetchAll(PDO::FETCH_ASSOC);
        $updatePlayerInfoEnabled = false;

        if (count($playerRecords) > 0 && $playerRecords[0]['player_update'] <= (time() - 60)) {
            $updatePlayerInfoEnabled = true;
        } else if (count($playerRecords) != $gameServerRecord['number_of_players']) {
            $updatePlayerInfoEnabled = true;
        }

        /*
         * players
         */

        $noResponse = false;
        $players = null;

        if ($updatePlayerInfoEnabled) {
            try {
                $ipAddress = $gameServerRecord['ip'];
                $queryPort = $gameServerRecord['query_port'];
                $srcServer = new GoldSrcServer($ipAddress, $queryPort);
                //$srcServer->initialize();
                //$serverInfo = $srcServer->getServerInfo();
                $players = $srcServer->getPlayers();
            } catch (Exception $e) {
                $noResponse = true;
            }
        }

        if ($updatePlayerInfoEnabled && !$noResponse) {
            $this->updatePlayers($gameServerId, $players);
            $statement->execute();
            $playerRecords = $statement->fetchAll(PDO::FETCH_ASSOC);

            $numberOfPlayers = count($playerRecords);
            $gameServerRecord['number_of_players'] = $numberOfPlayers;
            $gameServerRecord['game_server_update'] = time();
            $sql = "UPDATE `game_servers` SET `number_of_players` = :number_of_players, `game_server_update` = :game_server_update, `no_response_counter` = :no_response_counter WHERE `game_server_id` = :game_server_id";
            $serverUpdateStatement = $connection->prepare($sql);
            $serverUpdateStatement->bindParam(':number_of_players', $gameServerRecord['number_of_players']);
            $serverUpdateStatement->bindParam(':game_server_update', $gameServerRecord['game_server_update']);
            $serverUpdateStatement->bindValue(':no_response_counter', 0);
            $serverUpdateStatement->bindParam(':game_server_id', $gameServerId);
            $serverUpdateStatement->execute();
        }
        $gameServerRecord['players'] = $playerRecords;

        //  Update for No Response
        if ($noResponse && $gameServerRecord['no_response_counter'] == 0) {
            $sql = "UPDATE `game_servers` SET `no_response_counter` = :no_response_counter WHERE `game_server_id` = :game_server_id";
            $noResponseUpdateStatement = $connection->prepare($sql);
            $noResponseUpdateStatement->bindValue(':no_response_counter', 1);
            $noResponseUpdateStatement->bindParam(':game_server_id', $gameServerId);
            $noResponseUpdateStatement->execute();
        }

        return $gameServerRecord;
    }

    /**
     *  for statistics
     */
    public function insertCountryPlayers() {
        $connection = $this->getSqlConnection();
        $sql = "SELECT `country`, SUM(`number_of_players`) AS players FROM `game_servers` GROUP BY `country`";
        $statement = $connection->prepare($sql);
        $statement->execute();

        $time = time();
        $sql = "INSERT INTO `country_players` (`country_players_id`, `country`, `total_players`, `country_players_update`) VALUES (NULL, :country, :total_players, :country_players_update);";
        $countryPlayersInsertStatement = $connection->prepare($sql);
        $countryPlayersInsertStatement->bindParam(':country_players_update', $time, PDO::PARAM_INT);
        while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            $countryPlayersInsertStatement->bindParam(':country', $record['country'], PDO::PARAM_STR);
            $countryPlayersInsertStatement->bindParam(':total_players', $record['players'], PDO::PARAM_INT);
            $countryPlayersInsertStatement->execute();
        }
    }

    public function getNumberOfActiveServersPerCountry() {
        $connection = $this->getSqlConnection();
        $sql = "SELECT `country`, count(*) AS number_of_servers FROM `game_servers` WHERE `server_name` IS NOT NULL GROUP BY `country` ORDER BY `country`";
        $statement = $connection->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalNumberOfPlayersPerCountry($fromTime, $toTime) {
        $connection = $this->getSqlConnection();
        $sql = "SELECT `country`, SUM(`total_players`) as sum, count(*) as count FROM `country_players` WHERE `country_players_update` BETWEEN :fromTime AND :toTime GROUP BY `country` ORDER BY sum DESC";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':fromTime', $fromTime);
        $statement->bindParam(':toTime', $toTime);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAverageNumberOfPlayersPerCountry($fromTime, $toTime) {
        $connection = $this->getSqlConnection();
        $sql = "SELECT `country`, AVG(`total_players`) as avg, count(*) as count FROM `country_players` WHERE `country_players_update` BETWEEN :fromTime AND :toTime GROUP BY `country`";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':fromTime', $fromTime);
        $statement->bindParam(':toTime', $toTime);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNumberOfPlayersPerCountry($fromTime, $toTime) {
        $connection = $this->getSqlConnection();
        $sql = "SELECT `country`, MAX(`total_players`) as max, AVG(`total_players`) as avg, count(`country_players_id`) as count FROM `country_players` WHERE `country_players_update` BETWEEN :fromTime AND :toTime GROUP BY `country`";
        $statement = $connection->prepare($sql);
        $statement->bindParam(':fromTime', $fromTime, PDO::PARAM_INT);
        $statement->bindParam(':toTime', $toTime, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}