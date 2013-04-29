<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/21
 * Time: 17:54
 * To change this template use File | Settings | File Templates.
 */

require_once 'config.php';
require_once 'GameServerManager.php';
require_once 'MySmarty.php';

class Browser {

    /**
     * @var GameServerManager
     */
    private $gameServerManager;

    private $mySmarty;

    public $pageTitle = "Chivalry Server Browser";
    public $appRoot;
    public $countryCodeAssoc;
    public $numberOfActiveServersPerCountry;

    public $errorList = array();
    public $targetCountryCode = null;
    public $serverList = null;
    public $serverInfo = null;
    public $statistics = null;
    public $statisticsHeader = null;

    public function __construct() {
        $this->gameServerManager = GameServerManager::sharedManager();
        $this->mySmarty = new MySmarty();
        $this->countryCodeAssoc = get_country_assoc();
        $this->appRoot = 'http://' . HTTP_HOST . HTTP_PATH;

        if (isset($_GET['serverId'])) {
            $this->viewServerInfo($_GET['serverId']);
        } elseif (isset($_GET['statistics'])) {
            $this->viewStatistics();
        } else {
            $countryCodeArray = $this->getTargetCountryCodeArray();
            $this->viewServerList($countryCodeArray);
        }

    }

    private function getTargetCountryCodeArray() {
        $countryCode = geoip_country_code_by_addr(GameServerManager::getGeoIp(), $_SERVER['REMOTE_ADDR']);
        if (!$countryCode) {
            $countryCode = 'US';
        }
        $this->storeNumberOfActiveServersPerCountry($countryCode);

        if (isset($_GET['country']) && isset($this->countryCodeAssoc[$_GET['country']])) {
            $this->targetCountryCode = $_GET['country'];

            $this->pageTitle = "{$this->countryCodeAssoc[$_GET['country']]} - {$this->pageTitle}";

            return array($_GET['country']);
        } else {
            $this->targetCountryCode = $countryCode;
            return array($countryCode);
        }

    }

    private function storeNumberOfActiveServersPerCountry($necessaryCountryCode) {
        $list = $this->gameServerManager->getNumberOfActiveServersPerCountry();

        $this->numberOfActiveServersPerCountry = array();
        $necessaryFound = false;
        foreach ($list as $record) {
            $this->numberOfActiveServersPerCountry[] = array('country' => $record['country'], 'servers' => $record['number_of_servers']);
            if ($record['country'] == $necessaryCountryCode) {
                $necessaryFound = true;
            }
        }

        if ($necessaryFound) {
            return;
        }

        $this->numberOfActiveServersPerCountry[] = array('country' => $necessaryCountryCode, 'servers' => 0);
    }

    private function viewServerList($countryCodeArray) {
        $this->serverList = $this->gameServerManager->getServerList($countryCodeArray);
        $this->mySmarty->assign('data', $this);
        $this->mySmarty->display('list.html');
    }

    private function viewServerInfo($serverId) {
        $this->serverInfo = $this->gameServerManager->getServerInfo($serverId);

        if (!$this->serverInfo) {
            $this->pageTitle = "Error - {$this->pageTitle}";
            $this->errorList[] = 'The Information about the server is not available.';
        } else {
            $this->pageTitle = "{$this->serverInfo['server_name']} - {$this->pageTitle}";
            $this->serverInfo['country_name'] = $this->countryCodeAssoc[$this->serverInfo['country']];

            $lastUpdate = $this->convertSecToHMS(time() - $this->serverInfo['game_server_update']);
            $this->serverInfo['last_update'] = "{$lastUpdate} ago";
        }

        $this->mySmarty->assign('data', $this);
        $this->mySmarty->display('list.html');
    }

    public function convertSecToHMS($time) {
        $sec = $time % 60;
        $time = floor($time / 60);
        if ($time == 0) {
            return $sec . 's';
        }

        $result = $this->makeZerofillNumber($sec, 2) . 's';
        $min = $time % 60;
        $hour = floor($time / 60);
        if ($hour == 0) {
            $result = $min . 'm ' . $result;
            return $result;
        }

        $result = $hour . 'h ' . $this->makeZerofillNumber($min, 2) . 'm ' . $result;
        return $result;
    }

    private function makeZerofillNumber($number, $digit) {
        $result = '00000000' . $number;
        $result = substr($result, -$digit);
        return $result;
    }

    private function makeStatistics() {
        $baseTime = time();

        $baseTotalNumOfPlayersList = $this->gameServerManager->getTotalNumberOfPlayersPerCountry($baseTime - 86399, $baseTime);
        if (!count($baseTotalNumOfPlayersList)) {
            return array();
        }

        $this->statisticsHeader = array();
        $this->statisticsHeader[] = '';

        $totalNumOfPlayersAssoc = array();
        foreach ($baseTotalNumOfPlayersList as $record) {
            $totalNumOfPlayersAssoc[$record['country']] = $record;
        }

        $outputTable = array();
        $numOfColumns = 24;
        foreach ($baseTotalNumOfPlayersList as $record) {
            $columns = array();
            for ($i = 0; $i < $numOfColumns; $i++) {
                $columns[] = 0;
            }
            $country = $record['country'];
            $outputTable[$country] = $columns;
        }

        $interval = 60 * 60;
        for ($i = 0; $i < $numOfColumns; $i++) {
            $this->statisticsHeader[] = $i;

            $fromTime = $baseTime - $interval * ($i + 1) + 1;
            $toTime = $baseTime - $interval * $i;
            $avgNumOfPlayersList = $this->gameServerManager->getAverageNumberOfPlayersPerCountry($fromTime, $toTime);

            foreach ($avgNumOfPlayersList as $record) {
                $country = $record['country'];
                $avgNumOfPlayers = ceil($record['avg']);
                $outputTable[$country][$i] = $avgNumOfPlayers;
            }
        }

//        $numOfSamples = $baseTotalNumOfPlayersList[0]['count'];
//        foreach ($baseTotalNumOfPlayersList as $record) {
//            $country = $record['country'];
//            $sum = $record['sum'];
//            $avg = $sum / $numOfSamples;
//            $outputTable[$country] = $avg;
//        }

        //var_dump($outputTable);

        return $outputTable;
    }

    public function viewStatistics() {
        $this->pageTitle = "Statistics - {$this->pageTitle}";
        $this->statistics = $this->makeStatistics();
        $this->mySmarty->assign('data', $this);
        $this->mySmarty->display('list.html');
    }
}