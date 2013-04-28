<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/06
 * Time: 0:56
 * To change this template use File | Settings | File Templates.
 */

//echo dirname(__DIR__);
//phpinfo();
//var_dump($_SERVER);
//exit();
//$parentDir = pathinfo($_SERVER['PHP_SELF']);
//var_dump($parentDir);

require_once 'GameServerManager.php';

class TestManager {

    /**
     * @var GameServerManager
     */
    private $gameServerManager;

    public function __construct() {
        $this->gameServerManager = GameServerManager::sharedManager();
    }

    public function test() {
        $gameServerManager = $this->gameServerManager;

        for ($i=0; $i<100; $i++) {
            $gameServerManager->addGameServer(rand(1,254) . '.' . rand(1,254) . '.' . rand(1,254) . '.' . rand(1,254), rand(0, 100));
        }
    }

    public function testUpdateMaster() {
        $gameServerManager = $this->gameServerManager;
        $gameServerManager->updateWithMasterServer();
    }

    public function testUpdateIndividualServerInfo() {
        $gameServerManager = $this->gameServerManager;
        $gameServerManager->updateAllServerInfo();
    }

    public function testGetServerList() {
        $gameServerManager = $this->gameServerManager;
        $serverList = $gameServerManager->getServerList(array('JP', 'KR'));

        var_dump($serverList);
    }

    public function testUpdateSync() {
        $gameServerManager = $this->gameServerManager;
        $serverList = $gameServerManager->getServerList(array('JP', 'KR'));

        foreach ($serverList as $serverRecord) {
            $gameServerId = $serverRecord['game_server_id'];
            $gameServerManager->updateTargetServerInfo($gameServerId);
        }

    }

    /*
     * if the server runs php as module, the following process is better
     */
    public function testFsockopen() {
        $gameServerManager = $this->gameServerManager;
        $gameServerManager->updateWithMasterServer();
        $serverList = $gameServerManager->getServerList();


        $numberOfIdSet = ceil(count($serverList) / 20);
        $argSetArray = array();
        for ($i=0; $i<$numberOfIdSet; $i++) {
            $argSetArray[] = array();
        }

        $counter = 0;
        foreach ($serverList as $serverRecord) {
            $gameServerId = $serverRecord['game_server_id'];
            $argSetArray[$counter][] = $gameServerId;
            $counter++;
            $counter %= $numberOfIdSet;
        }

        define('HTTP_SERVER_PORT', 80);
        // making the php path for the fputs
        $fputsPhpPath = HTTP_PATH . 'updateTargetServer.php';
        $hostName = HTTP_HOST;
        // update each server asyncronous

        foreach ($argSetArray as $argSet) {
            if (!count($argSet)) {
                break;
            }

            $arg = implode(',', $argSet);

            $fp = fsockopen ($hostName, HTTP_SERVER_PORT, $errNo, $errStr, 5);
            if (!$fp) {
                echo "Error: $errStr ($errNo)<br>\n";
            } else {
                socket_set_blocking($fp, false);
                fputs ($fp, "GET {$fputsPhpPath}?serverIds={$arg} HTTP/1.0\r\nHost: {$hostName}\r\n\r\n");
                //for debug
                /*
                while (!feof($fp)) {
                    echo fgets($fp, 128);
                }
                */
            }
            fclose ($fp);
            echo "Current time: " . time() . " [{$arg}]<br />\n";
            usleep(100 * 1000);

        }
    }
}

$testManager = new TestManager();
//$testManager->test();
//$testManager->testUpdateMaster();
//$testManager->testUpdateIndividualServerInfo();
//$testManager->testGetServerList();
//$testManager->testUpdateSync();
//$testManager->testFsockopen();



