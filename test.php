<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/06
 * Time: 0:56
 * To change this template use File | Settings | File Templates.
 */

//phpinfo();

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
        $gameServerManager->updateIndividualServerInfo();
    }
}

$testManager = new TestManager();
//$testManager->test();
//$testManager->testUpdateMaster();
$testManager->testUpdateIndividualServerInfo();

