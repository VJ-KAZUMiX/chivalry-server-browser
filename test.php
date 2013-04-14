<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/06
 * Time: 0:56
 * To change this template use File | Settings | File Templates.
 */

//phpinfo();
//var_dump($_SERVER);
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
}

$testManager = new TestManager();
//$testManager->test();
//$testManager->testUpdateMaster();
//$testManager->testUpdateIndividualServerInfo();
//$testManager->testGetServerList();
//$testManager->testUpdateSync();



