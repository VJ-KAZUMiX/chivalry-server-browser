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

    public $multiCountries = null;

    public function __construct() {
        $this->gameServerManager = GameServerManager::sharedManager();
        $this->mySmarty = new MySmarty();
        $this->countryCodeAssoc = $this->gameServerManager->makeCountryAssoc();
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
        $result = null;

        $splitCountryCodeList = false;
        if (isset($_GET['country'])) {
            $splitCountryCodeList = explode(',', $_GET['country']);
        }

        $validateCountryCodeList = array();
        if ($splitCountryCodeList) {
            foreach ($splitCountryCodeList as $code) {
                if (isset($this->countryCodeAssoc[$code])) {
                    $validateCountryCodeList[] = $code;
                }
            }
        }

        switch (count($validateCountryCodeList)) {
            case 1:
                $this->targetCountryCode = $validateCountryCodeList[0];
                $this->pageTitle = "{$this->countryCodeAssoc[$validateCountryCodeList[0]]} - {$this->pageTitle}";
                $result =$validateCountryCodeList;
                $this->storeNumberOfActiveServersPerCountry($validateCountryCodeList[0]);
                break;

            case 0:
                $countryCode = geoip_country_code_by_addr(GameServerManager::getGeoIp(), $_SERVER['REMOTE_ADDR']);
                if (!$countryCode) {
                    $countryCode = 'JP';
                }
                $this->targetCountryCode = $countryCode;
                $result = array($countryCode);
                $this->storeNumberOfActiveServersPerCountry($countryCode);
                break;

            default:
                $this->multiCountries = $validateCountryCodeList;
                $this->pageTitle = implode(', ', $validateCountryCodeList) . " - {$this->pageTitle}";
                $result = $validateCountryCodeList;
                break;
        }

//        if (isset($_GET['country']) && isset($this->countryCodeAssoc[$_GET['country']])) {
//            // only 1 country is set
//            $this->targetCountryCode = $_GET['country'];
//
//            $this->pageTitle = "{$this->countryCodeAssoc[$_GET['country']]} - {$this->pageTitle}";
//
//            $result = $_GET['country'];
//        } else {
//            $countryCode = geoip_country_code_by_addr(GameServerManager::getGeoIp(), $_SERVER['REMOTE_ADDR']);
//            if (!$countryCode) {
//                $countryCode = 'JP';
//            }
//            $this->targetCountryCode = $countryCode;
//            $result = $countryCode;
//        }

        return $result;
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
            header("HTTP/1.0 404 Not Found");
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

    public function makeZerofillNumber($number, $digit) {
        $result = '00000000' . $number;
        $result = substr($result, -$digit);
        return $result;
    }

    private function makeStatistics() {
        $baseTime = time();
        $baseHour = $baseTime - $baseTime % 3600;
        if ($baseTime - $baseHour >= 60 * 2) {
            $baseTime = $baseHour + 3600;
        } else {
            $baseTime = $baseHour;
        }

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
            $numOfPlayersList = $this->gameServerManager->getNumberOfPlayersPerCountry($fromTime, $toTime);

            foreach ($numOfPlayersList as $record) {
                $country = $record['country'];
                if ($i === 0) {
                    $numOfPlayers = ceil($record['avg']);
                } else {
                    $numOfPlayers = ceil($record['max']);
                }

                $outputTable[$country][$i] = $numOfPlayers;
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